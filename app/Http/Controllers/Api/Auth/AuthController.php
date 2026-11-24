<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\SpinRuns;
use App\Models\Setting;
use App\Models\Wallet;
use App\Traits\Affiliates\AffiliateHistoryTrait;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Core\Tenancy\Tenancy;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    use AffiliateHistoryTrait;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.jwt', ['except' => ['login', 'register', 'submitForgetPassword', 'submitResetPassword']]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        try {
            $credentials = request(['email', 'password']);

            if (!$token = auth('api')->attempt($credentials)) {
                return response()->json(['error' => trans('Check credentials')], 400);
            }

            return $this->respondWithToken($token);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Could not create token'
            ], 400);
        }
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $setting = \Helper::getSetting();

            // Fields and validation come from the tenant's registration policy (no Brazil defaults).
            $rules = \App\Core\Localization\RegistrationPolicy::rules();

            $validator = \Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $userData = $request->only(array_values(array_intersect(['name', 'password', 'email', 'phone', 'cpf'], array_keys($rules))));
            if (! empty($userData['phone'])) {
                $userData['phone'] = \App\Core\Localization\RegistrationPolicy::normalizePhone((string) $userData['phone']);
            }
            if (! empty($userData['cpf']) && \App\Core\Localization\RegistrationPolicy::config()['document_type'] === 'cpf') {
                $userData['cpf'] = \Helper::soNumero($userData['cpf']);
            }
            $userData['language'] = app()->getLocale(); // the locale the player registered in

            /// criando dados do afiliado
            // Helper::getSetting() selects a fixed column list that omits these fields, so they were
            // always NULL (registration failed on strict-mode MySQL). Read them from the full row.
            $affiliateDefaults = Setting::query()->first(['revshare_percentage', 'cpa_value', 'cpa_baseline']);
            $userData['affiliate_revenue_share']    = (int) ($affiliateDefaults->revshare_percentage ?? 0);
            $userData['affiliate_cpa']              = $affiliateDefaults->cpa_value ?? 0;
            $userData['affiliate_baseline']         = $affiliateDefaults->cpa_baseline ?? 0;

            $user = new User();
            if($user->forceFill($userData)->save())
            {
                if(isset($request->reference_code) && !empty($request->reference_code)) {
                    // P20TUKHVRV
                    $checkAffiliate = User::where('inviter_code', $request->reference_code)->first();
                    if(!empty($checkAffiliate)) {
                        if($checkAffiliate->affiliate_revenue_share > 0 || $checkAffiliate->affiliate_cpa > 0) {
                            $user->forceFill(['inviter' => $checkAffiliate->id])->save();

                            self::saveAffiliateHistory($user);
                        }
                    }
                }

                if( $setting->disable_spin) {
                    if(!empty($request->spin_token)) {
                        self::redeemSpinToken((string) $request->spin_token, $user);
                    }
                }

                $credentials = $request->only(['email', 'password']);
                $token = auth('api')->attempt($credentials);
                if (!$token) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                return $this->respondWithToken($token);
            }

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'error' => trans('Registration could not be completed')
            ], 400);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitForgetPassword(Request $request)
    {
        $request->validate(["email" => "required|email|max:191"]);

        $genericResponse = response()->json([
            "status"  => true,
            "message" => trans("If the address is registered, a password reset link has been sent."),
        ], 200);

        // Same response whether or not the account exists (no account enumeration).
        $user = User::where("email", $request->email)->first();
        if (! $user) {
            return $genericResponse;
        }

        // 64-char CSPRNG token; only its hash is stored. One active token per email.
        $token = Str::random(64);
        DB::table("tenant_password_resets")->where("tenant_id", Tenancy::requireId())->where("email", $user->email)->delete();
        DB::table("tenant_password_resets")->insert([
            "tenant_id"  => Tenancy::requireId(),
            "email"      => $user->email,
            "token"      => Hash::make($token),
            "created_at" => Carbon::now(),
        ]);

        try {
            \Mail::send("emails.forget-password", [
                "token"     => $token,
                "resetLink" => url("/reset-password/".$token)."?email=".urlencode($user->email),
            ], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject(trans("Reset Password"));
            });
        } catch (\Throwable $e) {
            report($e);
        }

        return $genericResponse;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitResetPassword(Request $request)
    {
        $request->validate([
            "email"                 => "required|email|max:191",
            "password"              => ["required", "string", "confirmed", Rules\Password::min(8)],
            "password_confirmation" => "required",
            "token"                 => "required|string|size:64",
        ]);

        $invalid = response()->json(["error" => trans("This password reset link is invalid or has expired.")], 400);

        // The token is looked up BY EMAIL and verified against its stored hash: a token issued
        // for one account can no longer reset another account (previous account-takeover bug).
        $record = DB::table("tenant_password_resets")->where("tenant_id", Tenancy::requireId())->where("email", $request->email)->first();
        if (! $record || ! Hash::check($request->token, $record->token)) {
            return $invalid;
        }

        $ttlMinutes = (int) config("auth.passwords.users.expire", 60);
        if (Carbon::parse($record->created_at)->addMinutes($ttlMinutes)->isPast()) {
            DB::table("tenant_password_resets")->where("tenant_id", Tenancy::requireId())->where("email", $request->email)->delete();
            return $invalid;
        }

        $user = User::where("email", $request->email)->first();
        if (! $user) {
            return $invalid;
        }

        DB::transaction(function () use ($user, $request) {
            $user->forceFill(["password" => $request->password, "remember_token" => Str::random(60)])->save();
            // Single use.
            DB::table("tenant_password_resets")->where("tenant_id", Tenancy::requireId())->where("email", $request->email)->delete();
        });

        return response()->json(["status" => true, "message" => trans("Your password has been changed!")], 200);
    }


    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Credit a landing-page spin prize once. The token is the {signature, nonce} pair returned by
     * /api/spin/result; the run is atomically marked redeemed so it cannot be reused by other accounts.
     */
    private static function redeemSpinToken(string $spinToken, User $user): void
    {
        $obj = json_decode((string) base64_decode($spinToken, true));
        if (! is_object($obj) || ! is_string($obj->signature ?? null) || ! is_string($obj->nonce ?? null)) {
            return;
        }

        DB::transaction(function () use ($obj, $user) {
            $run = SpinRuns::where('key', $obj->signature)->where('nonce', $obj->nonce)->whereNull('redeemed_at')->lockForUpdate()->first();
            if (! $run) {
                return;
            }

            $claimed = SpinRuns::whereKey($run->id)->whereNull('redeemed_at')
                ->update(['redeemed_at' => now(), 'redeemed_by' => $user->id]);

            $value = json_decode((string) $run->prize)->value ?? null;
            $wallet = Wallet::where('user_id', $user->id)->first();
            $minor  = is_numeric($value) ? (int) round(((float) $value) * 100) : 0;
            if ($claimed === 1 && $wallet && $minor > 0) {
                \App\Core\Wallet\WalletService::credit($wallet, 'balance_bonus', \App\Core\Money\Amount::fromMinor($minor),
                    'bonus.spin', 'bonus', "bonus:spin:{$run->id}", ['reference_type' => 'spin_run', 'reference_id' => $run->id]);
            }
        });
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => auth('api')->user(),
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
