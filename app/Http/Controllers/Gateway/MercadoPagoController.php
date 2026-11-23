<?php

namespace App\Http\Controllers\Gateway;

use App\Core\Security\CallbackLog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\Concerns\HandlesWithdrawals;
use App\Models\SuitPayPayment;
use App\Traits\Gateways\MercadoPagoTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MercadoPagoController extends Controller
{
    use MercadoPagoTrait, HandlesWithdrawals;

    public function callbackMethodPayment(Request $request)
    {
        CallbackLog::record('payment', 'mercadopago.payout', $request, 'ignored', 'payout callbacks are logged only');
        return response()->json([], 200);
    }

    /**
     * Deposit callback. The body only identifies *which* payment to check; the status,
     * amount and currency are fetched from the gateway server-to-server before crediting.
     */
    public function callbackMethod(Request $request)
    {
        // Mercado Pago notifications carry {"type":"payment","data":{"id":...}}; the legacy
        // frontend format {"idTransaction":...} is still accepted as a hint.
        $id = (string) ($request->input('data.id') ?? $request->input('idTransaction', ''));

        if ($id === '') {
            CallbackLog::record('payment', 'mercadopago', $request, 'rejected', 'missing payment id');
            return response()->json(['status' => false], 400);
        }

        $settled = self::mpVerifyAndSettle($id);
        CallbackLog::record('payment', 'mercadopago', $request, $settled ? 'accepted' : 'rejected', $settled ? null : 'not approved by gateway, mismatched, or already settled', $id);

        return response()->json([], $settled ? 200 : 400);
    }

    public function getQRCodePix(Request $request)
    {
        return self::requestQrcodeMercadoPago($request);
    }

    public function consultStatusTransactionPix(Request $request)
    {
        return self::consultStatusTransactionMP($request);
    }

    protected function sendPayout(Model $withdrawal, string $type): bool
    {
        $payment = SuitPayPayment::create([
            'withdrawal_id' => $withdrawal->id,
            'user_id'       => $withdrawal->user_id,
            'pix_key'       => $withdrawal->pix_key,
            'pix_type'      => $withdrawal->pix_type,
            'amount'        => $withdrawal->amount,
            'observation'   => 'mercadopago:'.$type,
        ]);

        return (bool) self::pixCashOutMP([
            'pix_key'        => $withdrawal->pix_key,
            'pix_type'       => $withdrawal->pix_type,
            'amount'         => $withdrawal->amount,
            'suitpayment_id' => $payment->id,
        ]);
    }
}
