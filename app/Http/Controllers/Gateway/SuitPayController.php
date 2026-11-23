<?php

namespace App\Http\Controllers\Gateway;

use App\Core\Security\CallbackLog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\HandlesWithdrawals;
use App\Models\SuitPayPayment;
use App\Traits\Gateways\SuitpayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SuitPayController extends Controller
{
    use SuitpayTrait, HandlesWithdrawals;

    /**
     * Payout status callback. Previously dumped raw bodies into the `debug` table.
     */
    public function callbackMethodPayment(Request $request)
    {
        CallbackLog::record('payment', 'suitpay.payout', $request, 'ignored', 'payout callbacks are logged only');
        return response()->json([], 200);
    }

    /**
     * Deposit callback. The body is treated only as a hint of *which* transaction changed:
     * the real status is fetched from SuitPay server-to-server before anything is credited.
     */
    public function callbackMethod(Request $request)
    {
        $id = (string) $request->input('idTransaction', '');

        if ($id === '') {
            CallbackLog::record('payment', 'suitpay', $request, 'rejected', 'missing idTransaction');
            return response()->json(['status' => false], 400);
        }

        $status = self::suitpayFetchStatus($id);

        if (in_array($status, ['PAID_OUT', 'PAYMENT_ACCEPT'], true)) {
            $settled = self::finalizePayment($id);
            CallbackLog::record('payment', 'suitpay', $request, $settled ? 'accepted' : 'ignored', $settled ? null : 'already settled or unknown', $id);
            return response()->json([], 200);
        }

        CallbackLog::record('payment', 'suitpay', $request, 'rejected', 'gateway status: '.($status ?? 'unavailable'), $id);
        return response()->json(['status' => false], 400);
    }

    public function getQRCodePix(Request $request)
    {
        return self::requestQrcode($request);
    }

    public function consultStatusTransactionPix(Request $request)
    {
        return self::consultStatusTransaction($request);
    }

    protected function sendPayout(Model $withdrawal, string $type): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'suitpay:'.$type,
        ]);

        return self::pixCashOut([
            'pix_key'        => $withdrawal->pix_key,
            'pix_type'       => $withdrawal->pix_type,
            'amount'         => $withdrawal->amount,
            'suitpayment_id' => $payment->id,
        ]);
    }
}
