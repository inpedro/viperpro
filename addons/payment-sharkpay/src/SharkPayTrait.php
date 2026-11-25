<?php

namespace Addons\Payments\SharkPay;

use App\Core\Payments\GatewayCredentials;

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

trait SharkPayTrait
{
    protected static $uriSharkPay = 'https://sharkpay.com.br/api/';

    /**
     * Generate Credentials
     * Metodo para gerar credenciais
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return void
     */
    private static function generateCredentialsSharkPay()
    {
        $response = Http::withBasicAuth(GatewayCredentials::get("payment.sharkpay", "public_key", "public_key"), GatewayCredentials::get("payment.sharkpay", "private_key", "private_key"))->post(self::$uriSharkPay.'auth');
        if($response->successful()) {
            $json = $response->json();
            $token = $json['success']['token'];

            if($token) {
                return $token;
            }

            return false;
        }
    }

    /**
     * Request QRCODE
     * Metodo para solicitar uma QRCODE PIX
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return array
     */
    public static function requestQrcodeSharkPay($request)
    {
        $setting = \Helper::getSetting();
        $rules = [
            'amount' => ['required', 'numeric', 'min:'.$setting->min_deposit, 'max:'.$setting->max_deposit],
            'cpf'    => ['required', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if($token = self::generateCredentialsSharkPay()) {
            $order = Transaction::create([
                'payment_id' => time(),
                'user_id' => auth('api')->user()->id,
                'payment_method' => 'pix',
                'price' => $request->amount,
                'currency' => $setting->currency_code,
                'accept_bonus' => $request->accept_bonus,
                'status' => 0
            ]);

            if($order) {
                $params = [
                    'amount'     => \Helper::amountPrepare($request->amount),
                    'email'      => auth('api')->user()->email,
                    'quantity'   => 1,
                    'discount'   => 0,
                    'invoice_no' => $order->id,
                    'due_date'   => Carbon::now(),
                    'tax'        => 0,
                    'notes'      => 'Recarga de R$'.$request->amount,
                    'item_name'  => 'Recarga',
                    'document'   => \Helper::soNumero($request->cpf),
                    'client'     => auth('api')->user()->name,
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '. $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post(self::$uriSharkPay.'pix/create', $params);

                if($response->successful()) {
                    $pix = $response->json();
                    $txid = $pix['invoice']['txid'];
                    $reference = $pix['invoice']['reference'];

                    $order->update(['payment_id' => $txid, 'reference' => $reference]);

                    return [
                        'status' => true,
                        'idTransaction' => $txid,
                        'qrcode' => $pix['invoice']['copy']
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
    }



    /**
     * @param $idTransaction
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return bool
     */
    public static function finalizePaymentSharpay($idTransaction) : bool
    {
        // Settlement is centralised, locked and idempotent. Callers must have verified the payment with the gateway first.
        return \App\Core\Payments\DepositSettlementService::settle((string) $idTransaction);
    }

    /**
     * @param $idTransaction
     * @param $amount
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return void
     */
    private static function generateDepositSharkPay($idTransaction, $amount)
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
    private static function generateTransactionSharkPay($idTransaction, $amount)
    {
        $setting = \Helper::getSetting();

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
     * @param $request
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return \Illuminate\Http\JsonResponse|void
     */
    public static function pixCashOutSharkPay(array $array): bool
    {
        if($token = self::generateCredentialsSharkPay()) {
            $suitPayPayment = SuitPayPayment::lockForUpdate()->find($array['suitpayment_id']);
            if(!empty($suitPayPayment)) {
                $user = User::find($suitPayPayment->user_id);
                $params = [
                    'amount'        => \Helper::amountPrepare($array['amount']),
                    'pixkey'        => $array['pix_key'],
                    'keytype'       => \Helper::checkPixKeyTypeSharkPay($array['pix_key']),
                    'document'      => $user->cpf,
                    'email'         => $user->email,
                    'description'   => $user->name,
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '. $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post(self::$uriSharkPay.'pixout/create', $params);

                if($response->successful()) {
                    $responseData = $response->json();

                    if(isset($responseData['withdraw']) && $responseData['withdraw']['status'] == 'PAID') {
                        if(!empty($suitPayPayment)) {
                            if($suitPayPayment->update(['status' => 1, 'payment_id' => $responseData['withdraw']['reference']])) {
                                return true;
                            }
                            return false;
                        }
                        return false;
                    }
                    return false;
                }
                return false;
            }
            return false;
        }
    }
}
