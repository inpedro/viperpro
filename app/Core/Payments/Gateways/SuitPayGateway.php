<?php

namespace App\Core\Payments\Gateways;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Security\TrustedEndpoint;
use App\Models\SuitPayPayment;
use App\Traits\Gateways\SuitpayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SuitPayGateway extends AbstractPaymentGateway
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
        foreach (['suitpay_cliente_id' => 'client id', 'suitpay_cliente_secret' => 'client secret'] as $col => $label) {
            if (self::gatewayAttribute($col) === '') {
                $problems[] = "SuitPay {$label} is not set";
            }
        }
        if (! TrustedEndpoint::isAllowed('suitpay', self::gatewayAttribute('suitpay_uri'))) {
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

    protected function callbackPaymentId(Request $request): string
    {
        return (string) $request->input('idTransaction', '');
    }
}
