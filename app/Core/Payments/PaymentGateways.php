<?php

namespace App\Core\Payments;

use App\Core\Payments\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

/**
 * Registry of installed payment gateway adapters (config/payments.php 'gateways').
 * Phase 3 replaces the static config list with the addon registry.
 */
class PaymentGateways
{
    /** @return array<string, class-string<PaymentGatewayInterface>> */
    public static function registered(): array
    {
        return (array) config('payments.gateways', []);
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
}
