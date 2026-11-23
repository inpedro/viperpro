<?php

namespace App\Core\Payments;

use App\Http\Controllers\Gateway\DigitoPayController;
use App\Http\Controllers\Gateway\MercadoPagoController;
use App\Http\Controllers\Gateway\SharkPayController;
use App\Http\Controllers\Gateway\SuitPayController;

/**
 * Transitional registry (Phase 1) mapping the configured default gateway to the
 * controller that knows how to pay out through it. Phase 2 replaces this with
 * PaymentGatewayInterface adapters resolved from the addon registry.
 */
class GatewayControllers
{
    private const MAP = [
        'suitpay'     => SuitPayController::class,
        'digitopay'   => DigitoPayController::class,
        'mercadopago' => MercadoPagoController::class,
        'sharkpay'    => SharkPayController::class,
    ];

    public static function forDefaultGateway(): ?object
    {
        $gateway = strtolower((string) \App\Helpers\Core::GetDefaultGateway());
        $class   = self::MAP[$gateway] ?? null;

        return $class ? app($class) : null;
    }
}
