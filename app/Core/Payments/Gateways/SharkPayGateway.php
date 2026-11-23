<?php

namespace App\Core\Payments\Gateways;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Security\TrustedEndpoint;
use App\Models\SuitPayPayment;
use App\Models\Transaction;
use App\Traits\Gateways\SharkPayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SharkPayGateway extends AbstractPaymentGateway
{
    use SharkPayTrait;

    public function getIdentifier(): string { return 'sharkpay'; }

    public function getName(): string { return 'SharkPay'; }

    public function getSupportedCurrencies(): array { return ['BRL']; }

    public function getSupportedCountries(): array { return ['BR']; }

    public function getCapabilities(): array
    {
        return [Capability::DEPOSIT, Capability::DEPOSIT_VERIFICATION, Capability::WITHDRAWAL];
    }

    public function validateConfiguration(): array
    {
        $problems = [];
        if (self::gatewayAttribute('public_key') === '' || self::gatewayAttribute('private_key') === '') {
            $problems[] = 'SharkPay public/private key is not set';
        }
        if (! TrustedEndpoint::isAllowed('sharkpay', self::$uriSharkPay)) {
            $problems[] = 'SharkPay API host is not in SHARKPAY_ALLOWED_HOSTS';
        }

        return $problems;
    }

    public function createDeposit(Request $request): mixed
    {
        return self::requestQrcodeSharkPay($request);
    }

    /** GET pix/txid/{id}; invoice.status == 1 means paid (as used by the existing integration). */
    public function verifyDeposit(string $paymentId): VerificationResult
    {
        if ($paymentId === '' || $this->validateConfiguration() !== []) {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        $token = self::generateCredentialsSharkPay();
        if (! $token) {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        $response = Http::timeout(15)->withToken($token)->acceptJson()->get(self::$uriSharkPay.'pix/txid/'.rawurlencode($paymentId));
        if (! $response->successful()) {
            return new VerificationResult(VerificationResult::UNKNOWN);
        }

        $status = $response->json('invoice.status');
        $status = $status === null ? null : (string) $status;

        return new VerificationResult(self::mapStatus($status, ['1']), null, null, $status);
    }

    public function createWithdrawal(Model $withdrawal, string $type): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'sharkpay:'.$type,
        ]);

        return (bool) self::pixCashOutSharkPay([
            'pix_key'        => $withdrawal->pix_key,
            'pix_type'       => $withdrawal->pix_type,
            'amount'         => $withdrawal->amount,
            'suitpayment_id' => $payment->id,
        ]);
    }

    protected function callbackPaymentId(Request $request): string
    {
        $id = (string) $request->input('payment_id', '');
        if ($id === '' && $request->filled('reference')) {
            $id = (string) Transaction::where('reference', (string) $request->input('reference'))->value('payment_id');
        }

        return $id;
    }
}
