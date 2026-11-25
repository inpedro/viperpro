<?php

namespace App\Core\Payments;

use App\Core\Payments\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

/**
 * Registry of payment gateway adapters: config/payments.php 'gateways' plus the
 * 'contributes.payment_gateways' of every loaded addon.
 */
class PaymentGateways
{
    /** @return array<string, class-string<PaymentGatewayInterface>> */
    public static function registered(): array
    {
        // Core-registered adapters (config) plus those contributed by loaded, enabled addons.
        return (array) config('payments.gateways', [])
            + app(\App\Core\Addons\AddonManager::class)->contributedPaymentGateways();
    }

    public static function get(string $id): PaymentGatewayInterface
    {
        $class = self::registered()[strtolower($id)] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException("Payment gateway [{$id}] is not installed");
        }

        return app($class);
    }

    public static function has(string $id): bool
    {
        return isset(self::registered()[strtolower($id)]);
    }

    /** The operator's default payout/deposit gateway (settings.default_gateway), or null. */
    public static function default(): ?PaymentGatewayInterface
    {
        $id = strtolower((string) \App\Helpers\Core::GetDefaultGateway());

        return self::has($id) ? self::get($id) : null;
    }

    /** @return PaymentGatewayInterface[] */
    public static function all(): array
    {
        return array_map(fn (string $class) => app($class), self::registered());
    }

    /**
     * Withdrawal methods players may request: the default gateway's payout methods plus "bank"
     * (a manual transfer the operator pays outside the platform and approves/cancels in admin).
     *
     * @return string[]
     */
    public static function payoutMethods(): array
    {
        $gateway = self::default();

        return array_values(array_unique(array_merge($gateway ? $gateway->getPayoutMethods() : [], ['bank'])));
    }
}
