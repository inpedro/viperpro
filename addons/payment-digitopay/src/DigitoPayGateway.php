<?php

namespace Addons\Payments\DigitoPay;

use App\Core\Payments\Gateways\AbstractPaymentGateway;
use App\Core\Payments\GatewayCredentials;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Security\TrustedEndpoint;
use App\Models\DigitoPayPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DigitoPayGateway extends AbstractPaymentGateway implements \App\Core\Payments\Contracts\SupportsManualPayout
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
        if (GatewayCredentials::get('payment.digitopay', 'client_id', 'digitopay_cliente_id') === '') {
            $problems[] = 'DigitoPay client id is not set';
        }
        if (GatewayCredentials::get('payment.digitopay', 'client_secret', 'digitopay_cliente_secret') === '') {
            $problems[] = 'DigitoPay client secret is not set';
        }
        if (! TrustedEndpoint::isAllowed('digitopay', GatewayCredentials::get('payment.digitopay', 'api_url', 'digitopay_uri'))) {
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

    public function manualPayout(array $data): bool
    {
        $payment = DigitoPayPayment::create([
            'withdrawal_id' => 0,
            'user_id'       => $data['user_id'] ?? 0,
            'pix_key'       => $data['pix_key'],
            'pix_type'      => $data['pix_type'],
            'amount'        => $data['amount'],
            'observation'   => 'digitopay:manual '.($data['observation'] ?? ''),
        ]);

        return (bool) self::digitoPayPixCashOut([
            'pix_key'  => $data['pix_key'],
            'pix_type' => $data['pix_type'],
            'amount'   => $data['amount'],
            'digitoPayPayment_id' => $payment->id,
        ]);
    }

    protected function callbackPaymentId(Request $request): string
    {
        return (string) data_get($request->all(), 'payload.id', '');
    }
}
