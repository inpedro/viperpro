<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\Affiliates\AffiliateHistoryTrait;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class SocialAuthController extends Controller
{
    use AffiliateHistoryTrait;

    /**
     * Login with social OAuth feature
     *
     */
    public function redirectToProvider($driver)
    {
        return Socialite::driver($driver)->redirect();
    }

    /**
     * @param $driver
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback($driver)
    {
        try {
            $user = Socialite::driver($driver)->user();
            $existing_user = User::where('oauth_id', $user->getId())->orWhere([
                ['oauth_id', '=', NULL],
                ['email', '=', $user->email]
            ])->first();

            if ($existing_user) {
                if(!empty($existing_user->inviter)) {
                    self::saveAffiliateHistory($existing_user);
                }

                Auth::login($existing_user);
                return redirect()->to(url('/'));
            } else {
                // The password used to be the user's e-mail address, i.e. anyone knowing the address
                // could log in with password auth. Social accounts get an unusable random password.
                $new_user = new User();
                $new_user->forceFill([
                    'name'       => $user->getName(),
                    'email'      => $user->getEmail(),
                    'oauth_id'   => $user->getId(),
                    'oauth_type' => $driver,
                    'password'   => \Illuminate\Support\Str::random(64),
                ])->save();

                event(new Registered($new_user));
                Auth::login($new_user);

                // (A second Wallet::create here duplicated the wallet that User::created already makes.)

                self::saveAffiliateHistory($new_user);
                return redirect()->to(url('/'))->with('success', trans('Your account has been created.'));
            }

        } catch (Exception $e) {
            return redirect()->to(url('/'))->with('error', 'Login with your social media account has failed, try again or register with email');
        }
    }

}
