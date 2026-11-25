<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Notifications\NewWithdrawalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $wallet = Wallet::whereUserId(auth('api')->id())->where('active', 1)->first();
        return response()->json(['wallet' => $wallet], 200);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function myWallet()
    {
        $wallets = Wallet::whereUserId(auth('api')->id())->get();
        return response()->json(['wallets' => $wallets], 200);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function setWalletActive($id)
    {
        // Ownership enforced: a player can only activate one of their own wallets.
        $wallet = Wallet::where("user_id", auth("api")->id())->whereKey($id)->first();
        if (! $wallet) {
            return response()->json(["error" => trans("Wallet not found")], 404);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($wallet) {
            Wallet::where("user_id", $wallet->user_id)->where("active", 1)->update(["active" => 0]);
            $wallet->update(["active" => 1]);
        });

        return response()->json(["wallet" => $wallet->fresh()], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestWithdrawal(Request $request)
    {
        $setting = Setting::first();

        /// Verificar se é afiliado
        If(auth('api')->check()) {

            // Methods come from the casino's payout gateway ("bank" = paid manually by the operator).
            if (! in_array($request->type, \App\Core\Payments\PaymentGateways::payoutMethods(), true)) {
                return response()->json(['error' => trans('Invalid withdrawal type')], 400);
            }

            if($request->type === 'pix') {
                $rules = [
                    'amount'        => ['required', 'numeric', 'min:'.$setting->min_withdrawal, 'max:'.$setting->max_withdrawal],
                    'pix_type'      => 'required',
                    'accept_terms'  => 'required',
                ];

                switch ($request->pix_type) {
                    case 'document':
                        $rules['pix_key'] = 'required|cpf_ou_cnpj';
                        break;
                    case 'email':
                        $rules['pix_key'] = 'required|email';
                        break;
                    default:
                        $rules['pix_key'] = 'required';
                        break;

                }
            }

            if($request->type === 'bank') {
                $rules = [
                    'amount'        => ['required', 'numeric', 'min:'.$setting->min_withdrawal, 'max:'.$setting->max_withdrawal],
                    'bank_info'     => 'required',
                    'accept_terms'  => 'required',
                ];
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            if($request->amount < 0) {
                return response()->json(['error' => trans('You must request an amount')], 400);
            }


            /// verificar o limite de saque
            if(!empty($setting->withdrawal_limit) && !empty($setting->withdrawal_period)) {
                switch ($setting->withdrawal_period) {
                    case 'daily':
                        $registrosDiarios = Withdrawal::where('user_id', auth('api')->user()->id)->whereDate('created_at', now()->toDateString())->count();
                        if($registrosDiarios >= $setting->withdrawal_limit) {
                            return response()->json(['error' => trans('You have already reached the daily withdrawal limit')], 400);
                        }
                        break;
                    case 'weekly':
                        $registrosDiarios = Withdrawal::where('user_id', auth('api')->user()->id)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
                        if($registrosDiarios >= $setting->withdrawal_limit) {
                            return response()->json(['error' => trans('You have already reached the weekly withdrawal limit')], 400);
                        }
                        break;
                    case 'monthly':
                        $registrosDiarios = Withdrawal::where('user_id', auth('api')->user()->id)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
                        if($registrosDiarios >= $setting->withdrawal_limit) {
                            return response()->json(['error' => trans('You have already reached the monthly withdrawal limit')], 400);
                        }
                        break;
                    case 'yearly':
                        $registrosDiarios = Withdrawal::where('user_id', auth('api')->user()->id)->whereYear('created_at', now()->year)->count();
                        if($registrosDiarios >= $setting->withdrawal_limit) {
                            return response()->json(['error' => trans('You have already reached the yearly withdrawal limit')], 400);
                        }
                        break;
                }
            }

            if($request->amount > $setting->max_withdrawal) {
                return response()->json(['error' => trans('You exceeded the maximum allowed of :amount', ['amount' => $setting->max_withdrawal])], 400);
            }

            if($request->accept_terms == true) {
                $attributes = ['type' => $request->type, 'currency' => $request->currency, 'symbol' => $request->symbol];
                if ($request->type === 'pix') {
                    $attributes += ['pix_key' => $request->pix_key, 'pix_type' => $request->pix_type];
                } else {
                    $attributes += ['bank_info' => $request->bank_info];
                }

                // Balance check + debit + record creation happen atomically (no double-spend under concurrency).
                $withdrawal = \App\Core\Payments\WithdrawalService::request(
                    \App\Core\Payments\WithdrawalService::TYPE_USER,
                    (int) auth('api')->id(),
                    (string) \Helper::amountPrepare($request->amount),
                    $attributes
                );

                if (! $withdrawal) {
                    return response()->json(['error' => trans('Insufficient balance')], 400);
                }

                foreach (User::where('role_id', 0)->get() as $admin) {
                    $admin->notify(new NewWithdrawalNotification(auth('api')->user()->name, $withdrawal->amount));
                }

                return response()->json([
                    'status' => true,
                    'message' => trans('Withdrawal requested successfully'),
                ], 200);
            }

            return response()->json(['error' => trans('You must accept the terms')], 400);
        }

        return response()->json(['error' => trans('The withdrawal could not be processed')], 400);
    }


}
