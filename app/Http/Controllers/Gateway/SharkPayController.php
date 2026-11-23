<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\DelegatesToGateway;

/**
 * Routes for the 'sharkpay' gateway. Behaviour: App\Core\Payments\Gateways (see config/payments.php).
 */
class SharkPayController extends Controller
{
    use DelegatesToGateway;

    protected function gatewayId(): string
    {
        return 'sharkpay';
    }
}
