<?php

namespace App\Core\Payments\Gateways;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Models\SuitPayPayment;
use App\Traits\Gateways\MercadoPagoTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MercadoPagoGateway extends AbstractPaymentGateway
{
    use MercadoPagoTrait;

    private const API = 'https://api.mercadopago.com/v1/payments/';

    public function getIdentifier(): string { return 'mercadopago'; }

    public function getName(): string { return 'Mercado Pago'; }

    /** Mercado Pago operates several LATAM currencies; this integration has only been used with BRL. */
    public function getSupportedCurrencies(): array { return ['BRL']; }

    public function getSupportedCountries(): array { return ['BR']; }

    public function getCapabilities(): array
    {
        return [Capability::DEPOSIT, Capability::DEPOSIT_VERIFICATION, Capability::AMOUNT_VERIFICATION, Capability::WITHDRAWAL];
    }

    public function validateConfiguration(): array
    {
        return self::gatewayAttribute('mp_access_token') === '' ? ['Mercado Pago access token is not set'] : [];
    }

    public function createDeposit(Request $request): mixed
    {
        return self::requestQrcodeMercadoPago($request);
    }

    /** GET /v1/payments/{id}; reports status, transaction_amount and currency_id. */
    public function verifyDeposit(string $paymentId): VerificationResult
    {
        $token = self::gatewayAttribute('mp_access_token');
        if ($paymentId === '' || ! ctype_digit($paymentId) || $token === '') {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        $response = Http::timeout(15)->withToken($token)->get(self::API.$paymentId);
        if (! $response->successful()) {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        $status = $response->json('status');
        $amount = $response->json('transaction_amount');

        return new VerificationResult(
            self::mapStatus(is_string($status) ? $status : null, ['approved'], ['rejected', 'cancelled', 'refunded', 'charged_back']),
            is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : null,
            is_string($response->json('currency_id')) ? $response->json('currency_id') : null,
            is_string($status) ? $status : null,
        );
    }

    public function createWithdrawal(Model $withdrawal, string $type): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'mercadopago:'.$type,
        ]);

        return (bool) self::pixCashOutMP([
            'pix_key'        => $withdrawal->pix_key,
            'pix_type'       => $withdrawal->pix_type,
            'amount'         => $withdrawal->amount,
            'suitpayment_id' => $payment->id,
        ]);
    }

    protected function callbackPaymentId(Request $request): string
    {
        // Notifications carry {"type":"payment","data":{"id":...}}; the legacy {"idTransaction"} is still accepted.
        return (string) ($request->input('data.id') ?? $request->input('idTransaction', ''));
    }
}
