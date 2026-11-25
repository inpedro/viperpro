<?php

namespace App\Core\Payments\Contracts;

use App\Core\Integrations\HealthStatus;
use App\Core\Money\Money;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Payments\Data\WebhookResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway adapter implements. Methods outside getCapabilities()
 * throw App\Core\Integrations\UnsupportedCapability.
 */
interface PaymentGatewayInterface
{
    public function getIdentifier(): string;

    public function getName(): string;

    /** @return string[] ISO currency codes; [] = not declared/unknown */
    public function getSupportedCurrencies(): array;

    /** @return string[] ISO 3166-1 alpha-2 */
    public function getSupportedCountries(): array;

    /** @return string[] Capability::* */
    public function getCapabilities(): array;

    public function supports(string $capability): bool;

    /** @return string[] configuration problems; [] = valid */
    public function validateConfiguration(): array;

    /** Starts a deposit for the authenticated player (returns the gateway-specific payload for the client). */
    public function createDeposit(Request $request): mixed;

    /** Server-to-server confirmation. The only basis on which a deposit may be credited. */
    public function verifyDeposit(string $paymentId): VerificationResult;

    /** Authenticates/verifies (server-to-server) and settles a deposit callback. */
    public function processWebhook(Request $request): WebhookResult;

    /** @return string[] payout methods this gateway can send (e.g. "pix"); [] = no payouts */
    public function getPayoutMethods(): array;

    public function createWithdrawal(Model $withdrawal, string $type): bool;

    public function verifyWithdrawal(string $payoutId): VerificationResult;

    public function getTransactionStatus(string $paymentId): ?string;

    public function refund(string $paymentId, Money $amount): bool;

    public function getHealthStatus(): HealthStatus;
}
