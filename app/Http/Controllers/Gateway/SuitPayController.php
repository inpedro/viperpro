<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\DelegatesToGateway;

/**
 * Routes for the 'suitpay' gateway. Behaviour: App\Core\Payments\Gateways (see config/payments.php).
 */
class SuitPayController extends Controller
{
    use DelegatesToGateway;

    protected function gatewayId(): string
    {
        return 'suitpay';
    }
}
