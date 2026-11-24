<?php

namespace App\Core\Security;

use App\Core\Audit\Audit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TOTP two-factor authentication for staff accounts (admin, super-admin; configurable).
 */
final class Mfa
{
    public static function isRequiredFor(User $user): bool
    {
        return (bool) config('security.mfa.enforce', true)
            && $user->hasRole((array) config('security.mfa.required_roles', ['admin', 'super-admin']));
    }

    public static function isEnabled(User $user): bool
    {
        return $user->mfa_enabled_at !== null && ! empty($user->mfa_secret);
    }

    /**
     * Confirm enrollment with a first code. Returns the plain recovery codes (shown once) or null.
     *
     * @return string[]|null
     */
    public static function enable(User $user, string $secret, string $code): ?array
    {
        $step = Totp::verify($secret, $code);
        if ($step === null) {
            return null;
        }

        $codes = array_map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)), range(1, 8));
        $user->forceFill([
            'mfa_secret'         => $secret,
            'mfa_enabled_at'     => now(),
            'mfa_last_step'      => $step,
            'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();

        Audit::record('security.mfa_enabled', 'user', $user->id);

        return $codes;
    }

    /** Verify a TOTP code (replay-protected) or consume a recovery code. */
    public static function verify(User $user, string $code): bool
    {
        if (! self::isEnabled($user)) {
            return false;
        }

        return DB::transaction(function () use ($user, $code) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            $step = Totp::verify((string) $fresh->mfa_secret, $code, $fresh->mfa_last_step !== null ? (int) $fresh->mfa_last_step : null);
            if ($step !== null) {
                $fresh->forceFill(['mfa_last_step' => $step])->save();
                return true;
            }

            $codes = (array) ($fresh->mfa_recovery_codes ?? []);
            foreach ($codes as $i => $hash) {
                if (Hash::check(strtoupper(trim($code)), $hash)) {
                    unset($codes[$i]);
                    $fresh->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();
                    Audit::record('security.mfa_recovery_code_used', 'user', $fresh->id, ['remaining' => count($codes)]);
                    return true;
                }
            }

            Audit::record('security.mfa_failed', 'user', $fresh->id);
            return false;
        });
    }

    public static function disable(User $user): void
    {
        $user->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null, 'mfa_recovery_codes' => null, 'mfa_last_step' => null])->save();
        Audit::record('security.mfa_disabled', 'user', $user->id);
    }
}
