<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\DelegatesToGateway;

/**
 * Routes for the 'mercadopago' gateway. Behaviour: App\Core\Payments\Gateways (see config/payments.php).
 */
class MercadoPagoController extends Controller
{
    use DelegatesToGateway;

    protected function gatewayId(): string
    {
        return 'mercadopago';
    }
}
