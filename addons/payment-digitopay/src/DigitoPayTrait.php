<?php

namespace Addons\Payments\DigitoPay;

use App\Core\Payments\GatewayCredentials;

use App\Models\AffiliateHistory;
use App\Models\Deposit;
use App\Models\DigitoPayPayment;
use App\Models\Gateway;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\NewDepositNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Core as Helper;

trait DigitoPayTrait
{
    /**
     * @var $uri
     * @var $clienteId
     * @var $clienteSecret
     */
    protected static string $bearerToken;
    protected static string $uri;

    /**
     * Generate Credentials
     * Metodo para gerar credenciais
     */
    private static function digitoPayGenerateCredentials()
    {
        self::$uri     = (string) (GatewayCredentials::get("payment.digitopay", "api_url", "digitopay_uri"));
        $clientId      = GatewayCredentials::get("payment.digitopay", "client_id", "digitopay_cliente_id");
        $clienteSecret = GatewayCredentials::get("payment.digitopay", "client_secret", "digitopay_cliente_secret");

        // Credentials are only ever sent to an allowlisted HTTPS host (see config/payments.php).
        if ($clientId === "" || $clienteSecret === "" || ! \App\Core\Security\TrustedEndpoint::isAllowed("digitopay", self::$uri)) {
            return false;
        }

        $token = Http::timeout(15)->post(self::$uri."api/token/api", [
            "clientId" => $clientId,
            "secret"   => $clienteSecret,
        ])->json("accessToken");

        if (! is_string($token) || $token === "") {
            return false;
        }

        self::$bearerToken = $token;
        return true;
    }

    /**
     * Request QRCODE
     * Metodo para solicitar uma QRCODE PIX
 * @return array
     */
    public static function digitoPayRequestQrcode($request)
    {
        $setting = Helper::getSetting();
        $rules = [
            'amount' => ['required', 'numeric', 'min:'.$setting->min_deposit, 'max:'.$setting->max_deposit],
            'cpf'    => ['required', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if (! self::digitoPayGenerateCredentials()) {
            return ['status' => false];
        }

        $response = Http::withToken(self::$bearerToken)->post(self::$uri.'api/deposit', [
            "dueDate" => Carbon::now()->addDay(),
            "paymentOptions" => ["PIX"],
            "person" => [
                "cpf" => Helper::soNumero($request->cpf),
                "name" => auth('api')->user()->name,
            ],
            "value" => $request->amount
        ]);

        if($response->successful()) {
            $responseData = $response->json();
            self::digitoPayGenerateTransaction($responseData['id'], Helper::amountPrepare($request->amount), false); /// gerando historico
            self::digitoPayGenerateDeposit($responseData['id'], Helper::amountPrepare($request->amount)); /// gerando deposito

            return [
                'status' => true,
                'idTransaction' => $responseData['id'],
                'qrcode' => $responseData['pixCopiaECola']
            ];
        }

        return [
            'status' => false,
        ];
    }


    /**
     * Server-to-server status lookup (the endpoint this integration already used for polling).
     */
    public static function digitoPayFetchStatus(string $idTransaction): ?string
    {
        if ($idTransaction === "" || ! self::digitoPayGenerateCredentials()) {
            return null;
        }

        $response = Http::timeout(15)->withToken(self::$bearerToken)->get(self::$uri."api/status/".rawurlencode($idTransaction));
        if (! $response->successful()) {
            return null;
        }

        $status = $response->json();
        return is_string($status) ? $status : null;
    }

    /**
     * @param $idTransaction

     * @return bool
     */
    public static function digitoPayFinalizePayment($idTransaction) : bool
    {
        // Settlement is centralised, locked and idempotent. Callers must have verified the payment with the gateway first.
        return \App\Core\Payments\DepositSettlementService::settle((string) $idTransaction);
    }

    /**
     * @param $idTransaction
     * @param $amount
     * @dev victormsalatiel - Corra de golpista, me chame no instagram
     * * Use Digitopay - o melhor gateway de pagamentos para sua plataforma - 048 98814-2566
     * @return void
     */
    private static function digitoPayGenerateDeposit($idTransaction, $amount)
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
     * * Use Digitopay - o melhor gateway de pagamentos para sua plataforma - 048 98814-2566
     * @return void
     */
    private static function digitoPayGenerateTransaction($idTransaction, $amount, $accept_bonus)
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
     * @return \Illuminate\Http\JsonResponse|void
     */
    public static function digitoPayPixCashOut(array $array): bool
    {
        if (! self::digitoPayGenerateCredentials()) {
            return false;
        }

        $response = Http::withToken(self::$bearerToken)->post(self::$uri.'api/withdraw', [
            "paymentOptions" => ["PIX"],
            "person" => [
                "pixKeyTypes" => self::FormatPixType($array['pix_type']),
                "pixKey" => $array['pix_key']
            ],
            "value" => $array['amount']
        ]);

        if($response->successful()) {
            $responseData = $response->json();
            $digitoPayPayment = DigitoPayPayment::lockForUpdate()->find($array['digitoPayPayment_id']);
            if(!empty($digitoPayPayment)) {
                if($digitoPayPayment->update(['status' => 1, 'payment_id' => $responseData['id']])) {
                    return true;
                }
                return false;
            }
            return $responseData['success'];
        }
        return false;
    }

    /**
     * @param $type
     * @return string|void
     */
    private static function FormatPixType($type)
    {
        switch ($type) {
            case 'email':
                return 'EMAIL';
            case 'document' && strlen($type) == 11:
                return 'CPF';
            case 'document' && strlen($type) == 14:
                return 'CNPJ';
            case 'randomKey':
                return 'EVP';
            case 'phoneNumber':
                return 'PHONE';
        }
    }

}
