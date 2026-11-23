<?php

namespace App\Http\Controllers\Gateway\Concerns;

use App\Core\Payments\Contracts\PaymentGatewayInterface;
use App\Core\Payments\DepositSettlementService;
use App\Core\Payments\PaymentGateways;
use App\Core\Security\CallbackLog;
use Illuminate\Http\Request;

/**
 * HTTP surface of a payment gateway. All logic lives in the gateway adapter
 * (App\Core\Payments\Gateways\*); controllers only name their gateway.
 */
trait DelegatesToGateway
{
    abstract protected function gatewayId(): string;

    protected function gateway(): PaymentGatewayInterface
    {
        return PaymentGateways::get($this->gatewayId());
    }

    /** Deposit callback: verified server-to-server and settled idempotently by the adapter. */
    public function callbackMethod(Request $request)
    {
        return response()->json([], $this->gateway()->processWebhook($request) ? 200 : 400);
    }

    /** Payout status callbacks are recorded only; payouts are confirmed by the admin flow. */
    public function callbackMethodPayment(Request $request)
    {
        CallbackLog::record('payment', $this->gatewayId().'.payout', $request, 'ignored', 'payout callbacks are logged only');

        return response()->json([], 200);
    }

    public function getQRCodePix(Request $request)
    {
        return $this->gateway()->createDeposit($request);
    }

    /** Client polling: same server-to-server verification as the webhook. */
    public function consultStatusTransactionPix(Request $request)
    {
        $id = (string) $request->input('idTransaction', '');
        $verification = $this->gateway()->verifyDeposit($id);

        if ($verification->isPaid()) {
            DepositSettlementService::settle($id, $verification->amount, $verification->currency);

            return response()->json(['status' => 'PAID']);
        }

        return response()->json(['status' => $verification->providerStatus ?? strtoupper($verification->status)], 400);
    }
}
