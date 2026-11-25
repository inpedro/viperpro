<?php

namespace App\Core\Payments\Gateways;

use App\Core\Integrations\HealthStatus;
use App\Core\Integrations\UnsupportedCapability;
use App\Core\Money\Money;
use App\Core\Payments\Contracts\PaymentGatewayInterface;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Payments\Data\WebhookResult;
use App\Core\Payments\DepositSettlementService;
use App\Core\Security\CallbackLog;
use Illuminate\Http\Request;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /** Extract the gateway payment id from an inbound callback ('' if absent). The body is a hint only. */
    abstract protected function callbackPaymentId(Request $request): string;

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    public function getSupportedCountries(): array
    {
        return [];
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $source = $this->getIdentifier();
        $id = $this->callbackPaymentId($request);

        if ($id === '') {
            CallbackLog::record('payment', $source, $request, 'rejected', 'missing payment id');
            return WebhookResult::rejected('missing payment id');
        }

        return $this->verifyAndSettle($id, $request);
    }

    /** Server-to-server verification followed by idempotent settlement (shared by all adapters). */
    protected function verifyAndSettle(string $id, Request $request): WebhookResult
    {
        $source = $this->getIdentifier();
        $verification = $this->verifyDeposit($id);

        if (! $verification->isPaid()) {
            $reason = 'gateway status: '.($verification->providerStatus ?? $verification->status);
            CallbackLog::record('payment', $source, $request, 'rejected', $reason, $id);
            return WebhookResult::rejected($reason);
        }

        if (DepositSettlementService::settle($id, $verification->amount, $verification->currency)) {
            CallbackLog::record('payment', $source, $request, 'accepted', null, $id);
            return WebhookResult::credited();
        }

        // Paid at the gateway but not creditable here: already settled (a retry), unknown to this
        // tenant, or amount/currency mismatch (logged by the settlement service).
        CallbackLog::record('payment', $source, $request, 'ignored', 'already settled, unknown or mismatched', $id);
        return WebhookResult::acknowledged('not creditable');
    }

    public function getPayoutMethods(): array
    {
        return [];
    }

    public function getTransactionStatus(string $paymentId): ?string
    {
        return $this->verifyDeposit($paymentId)->providerStatus;
    }

    public function verifyWithdrawal(string $payoutId): VerificationResult
    {
        throw UnsupportedCapability::for($this->getName(), 'withdrawal verification', 'Not implemented by the existing integration; verify payouts in the gateway dashboard.');
    }

    public function refund(string $paymentId, Money $amount): bool
    {
        throw UnsupportedCapability::for($this->getName(), 'refunds', 'Not implemented by the existing integration.');
    }

    public function getHealthStatus(): HealthStatus
    {
        $problems = $this->validateConfiguration();
        if ($problems !== []) {
            return new HealthStatus(HealthStatus::DISABLED, $problems);
        }

        // Configured, but none of the legacy adapters has been verified against provider docs/sandbox yet.
        return new HealthStatus(HealthStatus::UNVERIFIED, ['Configured. Integration not yet verified against official documentation (Phase 7).']);
    }

    protected static function mapStatus(?string $providerStatus, array $paid, array $failed = []): string
    {
        return match (true) {
            $providerStatus === null => VerificationResult::UNKNOWN,
            in_array($providerStatus, $paid, true) => VerificationResult::PAID,
            in_array($providerStatus, $failed, true) => VerificationResult::FAILED,
            default => VerificationResult::PENDING,
        };
    }

    protected static function gatewayAttribute(string $column): string
    {
        return (string) (\App\Models\Gateway::first()?->getAttributes()[$column] ?? '');
    }
}
