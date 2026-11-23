<?php

namespace App\Http\Controllers\Gateway;

use App\Core\Security\CallbackLog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\HandlesWithdrawals;
use App\Models\SuitPayPayment;
use App\Models\Transaction;
use App\Traits\Gateways\SharkPayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SharkPayController extends Controller
{
    use SharkPayTrait, HandlesWithdrawals;

    public function callbackMethodPayment(Request $request)
    {
        CallbackLog::record('payment', 'sharkpay.payout', $request, 'ignored', 'payout callbacks are logged only');
        return response()->json([], 200);
    }

    /**
     * Deposit callback. The body only identifies *which* payment to check; the status,
     * amount and currency are fetched from the gateway server-to-server before crediting.
     */
    public function callbackMethod(Request $request)
    {
        $id = (string) $request->input('payment_id', '');
        if ($id === '' && $request->filled('reference')) {
            $id = (string) Transaction::where('reference', (string) $request->input('reference'))->value('payment_id');
        }

        if ($id === '') {
            CallbackLog::record('payment', 'sharkpay', $request, 'rejected', 'missing payment id');
            return response()->json(['status' => false], 400);
        }

        $settled = self::sharkpayVerifyAndSettle($id);
        CallbackLog::record('payment', 'sharkpay', $request, $settled ? 'accepted' : 'rejected', $settled ? null : 'not approved by gateway, mismatched, or already settled', $id);

        return response()->json([], $settled ? 200 : 400);
    }

    public function getQRCodePix(Request $request)
    {
        return self::requestQrcodeSharkPay($request);
    }

    public function consultStatusTransactionPix(Request $request)
    {
        return self::consultStatusTransactionSharkpay($request);
    }

    protected function sendPayout(Model $withdrawal, string $type): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'sharkpay:'.$type,
        ]);

        return (bool) self::pixCashOutSharkPay([
            'pix_key'        => $withdrawal->pix_key,
            'pix_type'       => $withdrawal->pix_type,
            'amount'         => $withdrawal->amount,
            'suitpayment_id' => $payment->id,
        ]);
    }
}
