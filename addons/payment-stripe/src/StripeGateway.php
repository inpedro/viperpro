<?php

namespace Addons\Payments\Stripe;

use App\Core\Integrations\HealthStatus;
use App\Core\Integrations\UnsupportedCapability;
use App\Core\Money\Amount;
use App\Core\Money\Money;
use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Payments\Data\WebhookResult;
use App\Core\Payments\GatewayCredentials;
use App\Core\Payments\Gateways\AbstractPaymentGateway;
use App\Core\Security\CallbackLog;
use App\Core\Tenancy\Tenancy;
use App\Core\Wallet\WalletService;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe Checkout deposits (https://docs.stripe.com/payments/checkout).
 *
 *  - Deposit: a Checkout Session (mode=payment) created server-side with the amount and the
 *    player's wallet currency; the player is redirected to session.url.
 *  - Webhook: Stripe-Signature verified with the tenant's endpoint secret (HMAC-SHA256 with
 *    timestamp tolerance), then the session is re-fetched from the API and settled with its
 *    amount_total/currency. checkout.session.completed and .async_payment_succeeded are handled.
 *  - Refund: Refunds API on the session's PaymentIntent, with a matching ledger reversal.
 *  - Payouts: not supported (Stripe Checkout is pay-in only).
 */
class StripeGateway extends AbstractPaymentGateway
{
    public const ID = 'payment.stripe';

    /** Currencies with 2 decimal places only: the wallet engine stores 2 decimals (docs/LEDGER.md). */
    private const CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD', 'NZD', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'BRL', 'MXN', 'ALL'];

    public function getIdentifier(): string { return 'stripe'; }

    public function getName(): string { return 'Stripe'; }

    public function getSupportedCurrencies(): array { return self::CURRENCIES; }

    public function getCapabilities(): array
    {
        return [Capability::DEPOSIT, Capability::DEPOSIT_VERIFICATION, Capability::AMOUNT_VERIFICATION, Capability::SIGNED_WEBHOOKS, Capability::REFUND];
    }

    public function validateConfiguration(): array
    {
        $problems = [];
        $key = $this->secretKey();
        if ($key === '') {
            $problems[] = 'Stripe secret key is not set';
        } elseif (! preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
            $problems[] = 'Stripe secret key has an unexpected format (expected sk_live_/sk_test_/rk_...)';
        }
        if ($this->webhookSecret() === '') {
            $problems[] = 'Stripe webhook signing secret (whsec_...) is not set';
        }

        return $problems;
    }

    public function getHealthStatus(): HealthStatus
    {
        $problems = $this->validateConfiguration();
        if ($problems !== []) {
            return new HealthStatus(HealthStatus::DISABLED, $problems);
        }

        return new HealthStatus(HealthStatus::OK, [str_starts_with($this->secretKey(), 'sk_test_') ? 'Configured (test mode).' : 'Configured (live mode).']);
    }

    public function createDeposit(Request $request): mixed
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['error' => trans('You need to be logged in to play')], 401);
        }

        $setting = \Helper::getSetting();
        $data = $request->validate([
            'amount'       => ['required', 'numeric', 'min:'.$setting->min_deposit, 'max:'.$setting->max_deposit],
            'accept_bonus' => ['nullable', 'boolean'],
        ]);

        $wallet = Wallet::where('user_id', $user->id)->where('active', 1)->first() ?? Wallet::where('user_id', $user->id)->firstOrFail();
        $currency = strtoupper((string) $wallet->currency);
        if (! in_array($currency, self::CURRENCIES, true)) {
            return response()->json(['error' => trans('Payment method not available')], 422);
        }

        $amount = Money::of((string) Amount::normalize((string) round((float) $data['amount'], 2)), $currency);
        $reference = (string) Str::uuid();

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode'                => 'payment',
                'client_reference_id' => (string) $user->id,
                'customer_email'      => $user->email,
                'line_items'          => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => strtolower($currency),
                        'unit_amount'  => $amount->minor,
                        'product_data' => ['name' => trans('Deposit').' · '.($setting->software_name ?? '')],
                    ],
                ]],
                'metadata'            => ['tenant_id' => (string) Tenancy::id(), 'user_id' => (string) $user->id, 'reference' => $reference],
                'success_url'         => url('/stripe/success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'          => url('/stripe/cancel'),
            ], ['idempotency_key' => 'deposit-'.$reference]);
        } catch (ApiErrorException $e) {
            Log::warning('stripe.checkout.create_failed', ['code' => $e->getStripeCode(), 'status' => $e->getHttpStatus()]);
            return response()->json(['error' => trans('Payment could not be started')], 502);
        }

        DB::transaction(function () use ($session, $user, $amount, $currency, $wallet, $data, $reference) {
            Transaction::create([
                'payment_id' => $session->id, 'reference' => $reference, 'user_id' => $user->id, 'payment_method' => 'stripe',
                'price' => $amount->toDecimal(), 'currency' => $currency, 'accept_bonus' => (bool) ($data['accept_bonus'] ?? false), 'status' => 0,
            ]);
            Deposit::create([
                'payment_id' => $session->id, 'user_id' => $user->id, 'amount' => $amount->toDecimal(), 'type' => 'stripe',
                'currency' => $currency, 'symbol' => $wallet->symbol, 'status' => 0,
            ]);
        });

        return response()->json(['status' => true, 'id' => $session->id, 'checkout_url' => $session->url]);
    }

    public function verifyDeposit(string $paymentId): VerificationResult
    {
        if (! str_starts_with($paymentId, 'cs_') || $this->secretKey() === '') {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        try {
            $s = $this->client()->checkout->sessions->retrieve($paymentId);
        } catch (ApiErrorException) {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        // The session must belong to this tenant (webhooks are routed by domain).
        if ((string) ($s->metadata['tenant_id'] ?? '') !== (string) Tenancy::id()) {
            return new VerificationResult(VerificationResult::FAILED, null, null, 'tenant mismatch');
        }

        $status = match (true) {
            $s->payment_status === 'paid'  => VerificationResult::PAID,
            $s->status === 'expired'       => VerificationResult::FAILED,
            default                        => VerificationResult::PENDING,
        };

        return new VerificationResult($status, Amount::fromMinor((int) $s->amount_total), strtoupper((string) $s->currency), (string) $s->payment_status);
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $secret = $this->webhookSecret();
        if ($secret === '') {
            CallbackLog::record('payment', 'stripe', $request, 'rejected', 'webhook secret not configured');
            return WebhookResult::rejected('not configured');
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (\UnexpectedValueException|SignatureVerificationException) {
            CallbackLog::record('payment', 'stripe', $request, 'rejected', 'invalid signature or payload');
            return WebhookResult::rejected('invalid signature');
        }

        if (! in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            CallbackLog::record('payment', 'stripe', $request, 'ignored', 'event '.$event->type, $event->id);
            return WebhookResult::acknowledged('event not handled');
        }

        // The signed event is still only a hint: re-fetch the session and settle from the API's view.
        return $this->verifyAndSettle((string) $event->data->object->id, $request);
    }

    protected function callbackPaymentId(Request $request): string
    {
        return ''; // processWebhook() is overridden; the body is never trusted unsigned
    }

    public function createWithdrawal(Model $withdrawal, string $type): bool
    {
        return false; // pay-in only; getPayoutMethods() is empty so this is never offered
    }

    /**
     * Refund a settled Checkout deposit. The ledger reversal (debit of the credited bucket) is
     * posted first and compensated if Stripe rejects the refund, so a refund never leaves the
     * player with both the money and the balance.
     */
    public function refund(string $paymentId, Money $amount): bool
    {
        $tx = Transaction::where('payment_id', $paymentId)->where('status', 1)->first();
        if (! $tx) {
            throw UnsupportedCapability::for('Stripe', 'refund of an unsettled or unknown deposit');
        }

        $wallet = Wallet::where('user_id', $tx->user_id)->firstOrFail();
        $bucket = \Helper::getSetting()->disable_rollover ? 'balance_withdrawal' : 'balance';
        $key = "refund:stripe:{$paymentId}:{$amount->minor}";

        $debit = WalletService::debit($wallet, $bucket, $amount, 'deposit.refund', 'deposits', $key, ['reference_type' => 'transaction', 'reference_id' => $tx->id]);
        if (! $debit->ok) {
            return false; // the player already spent the funds: refund must be handled manually
        }
        if ($debit->duplicate) {
            return true;
        }

        try {
            $session = $this->client()->checkout->sessions->retrieve($paymentId);
            $this->client()->refunds->create(['payment_intent' => $session->payment_intent, 'amount' => $amount->minor], ['idempotency_key' => $key]);

            return true;
        } catch (ApiErrorException $e) {
            WalletService::credit($wallet, $bucket, $amount, 'deposit.refund_failed', 'deposits', $key.':compensation');
            Log::warning('stripe.refund_failed', ['payment' => $paymentId, 'code' => $e->getStripeCode()]);

            return false;
        }
    }

    private function client(): StripeClient
    {
        return new StripeClient(['api_key' => $this->secretKey(), 'stripe_version' => '2023-10-16']);
    }

    private function secretKey(): string
    {
        return GatewayCredentials::get(self::ID, 'secret_key', 'stripe_secret_key');
    }

    private function webhookSecret(): string
    {
        $secret = GatewayCredentials::get(self::ID, 'webhook_secret');

        return $secret !== '' ? $secret : (string) config('services.stripe.webhook_secret');
    }
}
