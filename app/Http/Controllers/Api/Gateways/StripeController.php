<?php

namespace App\Http\Controllers\Api\Gateways;

use App\Core\Payments\DepositSettlementService;
use App\Core\Security\CallbackLog;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\Gateways\StripeTrait;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    use StripeTrait;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSessionRequest(Request $request)
    {
        try {
            return self::stripeSession($request->amount, $request->currency);
        }catch (\Exception $e) {
            report($e);
            return response()->json(['status' => false, 'errors' => trans('Payment could not be started')], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request)
    {
        try {
            return $this->stripePayment($request);
        }catch (\Exception $e) {
            report($e);
            return response()->json(['status' => false, 'errors' => trans('Payment could not be started')], 400);
        }
    }

    /**
     * Stripe Payment
     *
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    private function stripePayment(Request $request)
    {

    }

    /**
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getPublicKey()
    {
        // The secret key must never leave the server (it was previously returned here).
        $stripe = Gateway::first();
        return response()->json([
            "stripe_public_key" => $stripe?->stripe_public_key,
            "successURL"        => url("stripe/success"),
        ]);
    }

    /**
     * @param Request $request
     * @return void
     */
    public function webhooks(Request $request)
    {
        $secret = (string) config("services.stripe.webhook_secret");
        if ($secret === "") {
            CallbackLog::record("payment", "stripe", $request, "rejected", "STRIPE_WEBHOOK_SECRET not configured");
            return response()->json(["error" => "webhook not configured"], 503);
        }

        try {
            // Verifies the Stripe-Signature header (HMAC-SHA256) and the timestamp tolerance (replay protection).
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header("Stripe-Signature"),
                $secret
            );
        } catch (\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException) {
            CallbackLog::record("payment", "stripe", $request, "rejected", "invalid signature or payload");
            return response()->json(["error" => "invalid signature"], 400);
        }

        if ($event->type !== "checkout.session.completed") {
            CallbackLog::record("payment", "stripe", $request, "ignored", "event ".$event->type, $event->id);
            return response()->json(["received" => true]);
        }

        $session = $event->data->object;
        $user    = User::find($session->client_reference_id ?? null);

        if (! $user || ($session->payment_status ?? null) !== "paid" || empty($session->payment_intent)) {
            CallbackLog::record("payment", "stripe", $request, "ignored", "not a paid session for a known user", $event->id);
            return response()->json(["received" => true]);
        }

        $paymentId = (string) $session->payment_intent;
        $amount    = \App\Core\Money\Amount::fromMinor((int) $session->amount_total); // 2-decimal currencies only (see docs)
        $currency  = strtoupper((string) $session->currency);

        // Idempotent: Stripe retries webhooks; the unique payment_id + pending-state check prevent double credit.
        if (! Transaction::where("payment_id", $paymentId)->exists()) {
            self::generateTransaction($paymentId, $user->id, $amount);
            self::generateDeposit($paymentId, $user->id, $amount);
        }

        $wallet = Wallet::where("user_id", $user->id)->first();
        if (! $wallet || strcasecmp((string) $wallet->currency, $currency) !== 0) {
            // Previously this silently created a new wallet in another currency. Multi-currency wallets are Phase 2.
            CallbackLog::record("payment", "stripe", $request, "rejected", "currency mismatch with player wallet", $paymentId);
            return response()->json(["received" => true]);
        }

        $settled = DepositSettlementService::settle($paymentId, $amount);
        CallbackLog::record("payment", "stripe", $request, $settled ? "accepted" : "ignored", $settled ? null : "already settled", $paymentId);

        return response()->json(["received" => true]);
    }


    /**
     * @param $idTransaction
     * @param $amount
     * @return void
     */
    private static function generateDeposit($idTransaction, $userId, $amount)
    {
        $wallet = Wallet::where('user_id', $userId)->first();

        Deposit::create([
            'payment_id' => $idTransaction,
            'user_id' => $userId,
            'amount' => $amount,
            'type' => 'Stripe',
            'currency' => $wallet->currency,
            'symbol' => $wallet->symbol,
            'status' => 0
        ]);
    }

    /**
     * @param $idTransaction
     * @param $amount
     * @return void
     */
    private static function generateTransaction($idTransaction, $userId, $amount)
    {
        $setting = \Helper::getSetting();

        Transaction::create([
            'payment_id' => $idTransaction,
            'user_id' => $userId,
            'payment_method' => 'stripe',
            'price' => $amount,
            'currency' => $setting->currency_code,
            'status' => 0 // pending; DepositSettlementService moves it to 1 and credits the wallet exactly once
        ]);
    }
}
