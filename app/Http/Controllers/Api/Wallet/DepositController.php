<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\PaymentGateways;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    /**
     * Start a deposit through an installed, configured gateway. The client used to be able to
     * pick any gateway name, enabled or not; it must now be installed and pass its health check.
     */
    public function submitPayment(Request $request)
    {
        $id = strtolower((string) $request->input('gateway'));

        if (! PaymentGateways::has($id)) {
            return response()->json(['error' => trans('Payment method not available')], 422);
        }

        $gateway = PaymentGateways::get($id);
        if (! $gateway->supports(Capability::DEPOSIT) || ! $gateway->getHealthStatus()->isUsable()) {
            return response()->json(['error' => trans('Payment method not available')], 422);
        }

        return $gateway->createDeposit($request);
    }

    public function index()
    {
        $deposits = Deposit::whereUserId(auth('api')->id())->paginate();

        return response()->json(['deposits' => $deposits], 200);
    }
}
