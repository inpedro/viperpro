<?php

namespace App\Core\Payments\Gateways;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Security\TrustedEndpoint;
use App\Models\DigitoPayPayment;
use App\Traits\Gateways\DigitoPayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DigitoPayGateway extends AbstractPaymentGateway
{
    use DigitoPayTrait;

    public function getIdentifier(): string { return 'digitopay'; }

    public function getName(): string { return 'DigitoPay'; }

    public function getSupportedCurrencies(): array { return ['BRL']; }

    public function getSupportedCountries(): array { return ['BR']; }

    public function getCapabilities(): array
    {
        return [Capability::DEPOSIT, Capability::DEPOSIT_VERIFICATION, Capability::WITHDRAWAL];
    }

    public function validateConfiguration(): array
    {
        $problems = [];
        foreach (['digitopay_cliente_id' => 'client id', 'digitopay_cliente_secret' => 'client secret'] as $col => $label) {
            if (self::gatewayAttribute($col) === '') {
                $problems[] = "DigitoPay {$label} is not set";
            }
        }
        if (! TrustedEndpoint::isAllowed('digitopay', self::gatewayAttribute('digitopay_uri'))) {
            $problems[] = 'DigitoPay API URL is missing or not in DIGITOPAY_ALLOWED_HOSTS (empty by default)';
        }

        return $problems;
    }

    public function createDeposit(Request $request): mixed
    {
        return self::digitoPayRequestQrcode($request);
    }

    public function verifyDeposit(string $paymentId): VerificationResult
    {
        $status = self::digitoPayFetchStatus($paymentId);

        return new VerificationResult(self::mapStatus($status, ['REALIZADO']), null, null, $status);
    }

    public function createWithdrawal(Model $withdrawal, string $type): bool
    {
        $payment = DigitoPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'digitopay:'.$type,
        ]);

        return (bool) self::digitoPayPixCashOut([
            'pix_key'             => $withdrawal->pix_key,
            'pix_type'            => $withdrawal->pix_type,
            'amount'              => $withdrawal->amount,
            'digitoPayPayment_id' => $payment->id,
        ]);
    }

    protected function callbackPaymentId(Request $request): string
    {
        return (string) data_get($request->all(), 'payload.id', '');
    }
}
