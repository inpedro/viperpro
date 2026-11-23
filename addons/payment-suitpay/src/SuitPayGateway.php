<?php

namespace Addons\Payments\SuitPay;

use App\Core\Payments\Gateways\AbstractPaymentGateway;
use App\Core\Payments\GatewayCredentials;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Security\TrustedEndpoint;
use App\Models\SuitPayPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SuitPayGateway extends AbstractPaymentGateway implements \App\Core\Payments\Contracts\SupportsManualPayout
{
    use SuitpayTrait;

    public function getIdentifier(): string { return 'suitpay'; }

    public function getName(): string { return 'SuitPay'; }

    public function getSupportedCurrencies(): array { return ['BRL']; }

    public function getSupportedCountries(): array { return ['BR']; }

    public function getCapabilities(): array
    {
        // The status API returns only a status string (no amount), hence no AMOUNT_VERIFICATION.
        return [Capability::DEPOSIT, Capability::DEPOSIT_VERIFICATION, Capability::WITHDRAWAL];
    }

    public function validateConfiguration(): array
    {
        $problems = [];
        if (GatewayCredentials::get('payment.suitpay', 'client_id', 'suitpay_cliente_id') === '') {
            $problems[] = 'SuitPay client id is not set';
        }
        if (GatewayCredentials::get('payment.suitpay', 'client_secret', 'suitpay_cliente_secret') === '') {
            $problems[] = 'SuitPay client secret is not set';
        }
        if (! TrustedEndpoint::isAllowed('suitpay', GatewayCredentials::get('payment.suitpay', 'api_url', 'suitpay_uri'))) {
            $problems[] = 'SuitPay API URL is missing or not in SUITPAY_ALLOWED_HOSTS';
        }

        return $problems;
    }

    public function createDeposit(Request $request): mixed
    {
        return self::requestQrcode($request);
    }

    public function verifyDeposit(string $paymentId): VerificationResult
    {
        $status = self::suitpayFetchStatus($paymentId);

        return new VerificationResult(self::mapStatus($status, ['PAID_OUT', 'PAYMENT_ACCEPT']), null, null, $status);
    }

    public function createWithdrawal(Model $withdrawal, string $type): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'suitpay:'.$type,
        ]);

        return (bool) self::pixCashOut([
            'pix_key'        => $withdrawal->pix_key,
            'pix_type'       => $withdrawal->pix_type,
            'amount'         => $withdrawal->amount,
            'suitpayment_id' => $payment->id,
        ]);
    }

    public function manualPayout(array $data): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => 0,
            'user_id'       => $data['user_id'] ?? 0,
            'pix_key'       => $data['pix_key'],
            'pix_type'      => $data['pix_type'],
            'amount'        => $data['amount'],
            'observation'   => 'suitpay:manual '.($data['observation'] ?? ''),
        ]);

        return (bool) self::pixCashOut([
            'pix_key'  => $data['pix_key'],
            'pix_type' => $data['pix_type'],
            'amount'   => $data['amount'],
            'suitpayment_id' => $payment->id,
        ]);
    }

    protected function callbackPaymentId(Request $request): string
    {
        return (string) $request->input('idTransaction', '');
    }
}
