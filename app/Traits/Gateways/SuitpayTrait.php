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

trait SuitpayTrait
{
    /**
     * @var $uri
     * @var $clienteId
     * @var $clienteSecret
     */
    protected static string $uri;
    protected static string $clienteId;
    protected static string $clienteSecret;

    /**
     * Generate Credentials
     * Metodo para gerar credenciais
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return void
     */
    private static function generateCredentials()
    {
        $setting = Gateway::first();
        if (empty($setting)) {
            return false;
        }

        self::$uri           = $setting->getAttributes()["suitpay_uri"] ?? "";
        self::$clienteId     = $setting->getAttributes()["suitpay_cliente_id"] ?? "";
        self::$clienteSecret = $setting->getAttributes()["suitpay_cliente_secret"] ?? "";

        return self::$clienteId !== "" && self::$clienteSecret !== ""
            && \App\Core\Security\TrustedEndpoint::isAllowed("suitpay", self::$uri);
    }

    /**
     * Request QRCODE
     * Metodo para solicitar uma QRCODE PIX
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return array
     */
    public static function requestQrcode($request)
    {
        $setting = \Helper::getSetting();
        $rules = [
            'amount' => ['required', 'max:'.$setting->min_deposit, 'max:'.$setting->max_deposit],
            'cpf'    => ['required', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if (! self::generateCredentials()) {
            return ['status' => false];
        }

        $response = Http::withHeaders([
            'ci' => self::$clienteId,
            'cs' => self::$clienteSecret
        ])->post(self::$uri.'gateway/request-qrcode', [
            "requestNumber" => time(),
            "dueDate" => Carbon::now()->addDay(),
            "amount" => \Helper::amountPrepare($request->amount),
            "shippingAmount" => 0.0,
            "usernameCheckout" => "checkout",
            "callbackUrl" => url('/suitpay/callback'),
            "client" => [
                "name" => auth('api')->user()->name,
                "document" =>\Helper::soNumero($request->cpf),
                "phoneNumber" => \Helper::soNumero(auth('api')->user()->phone),
                "email" => auth('api')->user()->email
            ],
            //     "split" => [
            //         "username" => "encargos",
            //        "percentageSplit" => 20
            //    ]
        ]);

        if($response->successful()) {
            $responseData = $response->json();

            self::generateTransaction($responseData['idTransaction'], \Helper::amountPrepare($request->amount), $request->accept_bonus); /// gerando historico
            self::generateDeposit($responseData['idTransaction'], \Helper::amountPrepare($request->amount)); /// gerando deposito

            return [
                'status' => true,
                'idTransaction' => $responseData['idTransaction'],
                'qrcode' => $responseData['paymentCode']
            ];
        }

        return [
            'status' => false,
        ];
    }


    /**
     * Ask SuitPay (server-to-server, authenticated with our ci/cs credentials) for the
     * real status of a transaction. Never trust the status sent in an inbound callback.
     */
    public static function suitpayFetchStatus(string $idTransaction): ?string
    {
        if ($idTransaction === "" || ! self::generateCredentials()) {
            return null;
        }

        $response = Http::timeout(15)->withHeaders([
            "ci" => self::$clienteId,
            "cs" => self::$clienteSecret,
        ])->post(self::$uri."gateway/consult-status-transaction", [
            "typeTransaction" => "PIX",
            "idTransaction"   => $idTransaction,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $status = $response->json();
        return is_string($status) ? $status : null;
    }

    /**
     * @param $idTransaction
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return bool
     */
    public static function finalizePayment($idTransaction) : bool
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
    private static function generateDeposit($idTransaction, $amount)
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
    private static function generateTransaction($idTransaction, $amount, $accept_bonus)
    {
        $setting = \Helper::getSetting();

        Transaction::create([
            'payment_id' => $idTransaction,
            'user_id' => auth('api')->user()->id,
            'payment_method' => 'pix',
            'price' => $amount,
            'currency' => $setting->currency_code,
            'accept_bonus' => $accept_bonus,
            'status' => 0
        ]);
    }

    /**
     * @param $request
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * @return \Illuminate\Http\JsonResponse|void
     */
    public static function pixCashOut(array $array): bool
    {
        if (! self::generateCredentials()) {
            return false;
        }

        $response = Http::withHeaders([
            'ci' => self::$clienteId,
            'cs' => self::$clienteSecret
        ])->post(self::$uri.'gateway/pix-payment', [
            "key" => $array['pix_key'],
            "typeKey" => $array['pix_type'],
            "value" => $array['amount'],
            'callbackUrl' => url('/suitpay/payment'),
        ]);

        if($response->successful()) {
            $responseData = $response->json();

            if($responseData['response'] == 'OK') {
                $suitPayPayment = SuitPayPayment::lockForUpdate()->find($array['suitpayment_id']);
                if(!empty($suitPayPayment)) {
                    if($suitPayPayment->update(['status' => 1, 'payment_id' => $responseData['idTransaction']])) {
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
}
