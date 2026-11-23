<?php

namespace App\Traits\Gateways;

use App\Models\AffiliateHistory;
use App\Models\Deposit;
use App\Models\GamesKey;
use App\Models\Gateway;
use App\Models\Setting;
use App\Models\SuitPayPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\NewDepositNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Core as Helper;

trait MercadoPagoTrait
{

    /**
     * @param $idTransaction
     * @param $amount
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return void
     */
    private static function generateDepositMP($idTransaction, $amount)
    {
        $userId = auth('api')->user()->id;
        $wallet = Wallet::where('user_id', $userId)->first();

        Deposit::create([
            'payment_id'=> $idTransaction,
            'user_id'   => $userId,
            'amount'    => $amount,
            'type'      => 'pix',
            'currency'  => $wallet->currency,
            'symbol'    => $wallet->symbol,
            'status'    => 0
        ]);
    }

    /**
     * @param $idTransaction
     * @param $amount
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return void
     */
    private static function generateTransactionMP($idTransaction, $amount)
    {
        $setting = Helper::getSetting();

        Transaction::create([
            'payment_id' => $idTransaction,
            'user_id' => auth('api')->user()->id,
            'payment_method' => 'pix',
            'price' => $amount,
            'currency' => $setting->currency_code,
            'status' => 0
        ]);
    }

    /**
     * Request QRCODE
     * Metodo para solicitar uma QRCODE PIX
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return array
     */
    public static function requestQrcodeMercadoPago($request)
    {
        $setting = Helper::getSetting();
        $rules = [
            'amount' => ['required', 'max:'.$setting->min_deposit, 'max:'.$setting->max_deposit],
            'cpf'    => ['required', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $gateway = Gateway::first();
        $stringGenerate = Helper::generateRandomString(15);

        $token = Helper::MakeToken([
            'total' => $request->amount,
            'qty' => 1,
            'user_id' => auth('api')->user()->id
        ]);

        $response = Http::withHeaders([
            'X-Idempotency-Key' => $token,
            'Authorization' => 'Bearer ' . $gateway->mp_access_token,
        ])->post('https://api.mercadopago.com/v1/payments', [
            "transaction_amount" => floatval($request->amount),
            "description" => 'Pagamento',
            "payment_method_id" => "pix",
            "notification_url" => url('mercadopago/callback'),
            "external_reference" => $stringGenerate,
            "payer" => [
                "email" => auth('api')->user()->email,
                "first_name" => auth('api')->user()->name,
                "last_name" => auth('api')->user()->name,
                "identification" => [
                    "type" => "CPF",
                    "number" => Helper::soNumero($request->cpf)
                ]
            ]
        ]);

        if($response->successful()) {
            $resp = $response->json();

            if(isset($resp['id']) && !empty($resp['id'])) {
                self::generateTransactionMP($resp['id'], Helper::amountPrepare($request->amount), $request->accept_bonus); /// gerando historico
                self::generateDepositMP($resp['id'], Helper::amountPrepare($request->amount)); /// gerando deposito

                $transactionData = $resp['point_of_interaction']['transaction_data'];
                return [
                    'status' => true,
                    'qrcode' => $transactionData['qr_code'],
                    'idTransaction' => $resp['id'],
                ];
            }
            return [
                'status' => false,
            ];
        }

        return [
            'status' => false,
        ];
    }




    /**
     * @param $idTransaction
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return bool
     */
    public static function finalizePaymentMP($idTransaction) : bool
    {
        // Settlement is centralised, locked and idempotent. Callers must have verified the payment with the gateway first.
        return \App\Core\Payments\DepositSettlementService::settle((string) $idTransaction);
    }

    public static function pixCashOutMP($params)
    {

    }

}
