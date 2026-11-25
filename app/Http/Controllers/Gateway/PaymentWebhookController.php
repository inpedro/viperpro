<?php

namespace App\Http\Controllers\Gateway;

use App\Core\Payments\PaymentGateways;
use App\Core\Security\CallbackLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Unified inbound endpoint: POST /payments/webhooks/{gateway}.
 * The tenant comes from the domain (ResolveTenant); the gateway adapter authenticates and
 * verifies the request. Legacy per-gateway callback URLs keep working.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway)
    {
        if (! preg_match('/^[a-z0-9_-]{2,40}$/', $gateway) || ! PaymentGateways::has($gateway)) {
            CallbackLog::record('payment', substr($gateway, 0, 40), $request, 'rejected', 'gateway not active for this casino');
            return response()->json(['error' => 'unknown gateway'], 404);
        }

        $result = PaymentGateways::get($gateway)->processWebhook($request);

        return response()->json(['received' => $result->acknowledged], $result->httpStatus());
    }
}
