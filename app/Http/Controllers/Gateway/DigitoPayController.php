<?php

namespace App\Http\Controllers\Gateway;

use App\Core\Security\CallbackLog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\HandlesWithdrawals;
use App\Models\DigitoPayPayment;
use App\Traits\Gateways\DigitoPayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DigitoPayController extends Controller
{
    use DigitoPayTrait, HandlesWithdrawals;

    public function callbackMethodPayment(Request $request)
    {
        CallbackLog::record('payment', 'digitopay.payout', $request, 'ignored', 'payout callbacks are logged only');
        return response()->json([], 200);
    }

    /**
     * Deposit callback: the payload id is only a hint; status is re-checked with DigitoPay.
     */
    public function callbackMethod(Request $request)
    {
        $id = (string) data_get($request->all(), 'payload.id', '');

        if ($id === '') {
            CallbackLog::record('payment', 'digitopay', $request, 'rejected', 'missing payload.id');
            return response()->json(['status' => false], 400);
        }

        $status = self::digitoPayFetchStatus($id);

        if ($status === 'REALIZADO') {
            $settled = self::digitoPayFinalizePayment($id);
            CallbackLog::record('payment', 'digitopay', $request, $settled ? 'accepted' : 'ignored', $settled ? null : 'already settled or unknown', $id);
            return response()->json([], 200);
        }

        CallbackLog::record('payment', 'digitopay', $request, 'rejected', 'gateway status: '.($status ?? 'unavailable'), $id);
        return response()->json(['status' => false], 400);
    }

    public function getQRCodePix(Request $request)
    {
        return self::digitoPayRequestQrcode($request);
    }

    public function consultStatusTransactionPix(Request $request)
    {
        return self::digitoPayConsultStatusTransaction($request);
    }

    protected function sendPayout(Model $withdrawal, string $type): bool
    {
        $payment = DigitoPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'digitopay:'.$type,
        ]);

        return self::digitoPayPixCashOut([
            'pix_key'             => $withdrawal->pix_key,
            'pix_type'            => $withdrawal->pix_type,
            'amount'              => $withdrawal->amount,
            'digitoPayPayment_id' => $payment->id,
        ]);
    }
}
