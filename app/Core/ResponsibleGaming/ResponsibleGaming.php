<?php

namespace App\Core\ResponsibleGaming;

use App\Core\Audit\Audit;
use App\Core\Money\Amount;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Player protection: deposit limits, cool-off / self-exclusion, reality checks.
 *
 * Rules (common regulatory practice; operators must confirm the exact rules of their licence):
 *  - lowering or setting a limit applies immediately;
 *  - raising or removing a limit applies after a cooling-off period (24 h by default);
 *  - an exclusion cannot be shortened or lifted early; a permanent exclusion cannot be undone
 *    by the player;
 *  - excluded players cannot deposit or launch games, but can still withdraw.
 */
final class ResponsibleGaming
{
    public const PERIODS = ['daily' => 1, 'weekly' => 7, 'monthly' => 30];
    public const EXCLUSIONS = ['24h' => 1, '7d' => 7, '30d' => 30, '6m' => 182, '1y' => 365, 'permanent' => null];

    public static function for(User $user): PlayerProtection
    {
        $p = PlayerProtection::firstOrCreate(['user_id' => $user->id]);

        return self::applyMaturedLimits($p);
    }

    public static function isExcluded(User $user): bool
    {
        $p = PlayerProtection::where('user_id', $user->id)->first();

        return $p !== null && ($p->excluded_permanently || ($p->excluded_until !== null && $p->excluded_until->isFuture()));
    }

    /** null = allowed; otherwise a translated reason. */
    public static function depositBlockReason(User $user, string $amount): ?string
    {
        if (self::isExcluded($user)) {
            return trans('Your account is self-excluded. Deposits are not available.');
        }

        $p = self::for($user);
        $requested = Amount::toMinor($amount) ?? 0;

        foreach (self::PERIODS as $period => $days) {
            $limit = $p->{"deposit_limit_{$period}"};
            if ($limit === null) {
                continue;
            }

            // Settled AND pending deposits count, so a burst of pending requests cannot bypass the limit.
            $used = (float) Deposit::where('user_id', $user->id)->whereIn('status', [0, 1])
                ->where('created_at', '>=', now()->subDays($days))->sum('amount');
            $usedMinor = (int) round($used * 100);

            if ($usedMinor + $requested > (Amount::toMinor((string) $limit) ?? 0)) {
                $left = max(0, (Amount::toMinor((string) $limit) ?? 0) - $usedMinor);
                return trans('This deposit exceeds your :period deposit limit. You can deposit up to :amount.', [
                    'period' => trans($period), 'amount' => Amount::fromMinor($left),
                ]);
            }
        }

        return null;
    }

    /**
     * @param array<string, string|null> $limits period => decimal string or null (no limit)
     */
    public static function setDepositLimits(User $user, array $limits): PlayerProtection
    {
        $p = self::for($user);
        $delay = (int) config('responsible_gaming.increase_delay_hours', 24);
        $pending = false;

        foreach (self::PERIODS as $period => $_) {
            if (! array_key_exists($period, $limits)) {
                continue;
            }
            $new = $limits[$period] === null || $limits[$period] === '' ? null : Amount::normalize((string) $limits[$period]);
            $current = $p->{"deposit_limit_{$period}"};

            $isDecrease = $new !== null && ($current === null || (Amount::toMinor($new) < Amount::toMinor((string) $current)));

            if ($isDecrease) {
                $p->{"deposit_limit_{$period}"} = $new;      // stricter: immediate
                $p->{"pending_deposit_limit_{$period}"} = null;
            } elseif ($new !== $current) {
                // Looser (raise or removal): only after the cooling-off period.
                $p->{"pending_deposit_limit_{$period}"} = $new ?? '-1';
                $pending = true;
            }
        }

        if ($pending) {
            $p->pending_limits_effective_at = now()->addHours($delay);
        }
        $p->save();
        Audit::record('rg.limits_changed', 'user', $user->id, ['pending' => $pending]);

        return $p;
    }

    public static function exclude(User $user, string $duration): PlayerProtection
    {
        if (! array_key_exists($duration, self::EXCLUSIONS)) {
            throw new \InvalidArgumentException('Unknown exclusion period');
        }

        $p = self::for($user);
        $days = self::EXCLUSIONS[$duration];

        if ($days === null) {
            $p->excluded_permanently = true;
        } else {
            $until = now()->addDays($days);
            // Never shortens an existing exclusion.
            if ($p->excluded_until === null || $until->greaterThan($p->excluded_until)) {
                $p->excluded_until = $until;
            }
        }
        $p->save();

        // End all player sessions: tokens issued before now are rejected.
        $user->forceFill(['tokens_valid_after' => now()])->save();
        Audit::record('rg.self_excluded', 'user', $user->id, ['duration' => $duration]);

        return $p;
    }

    public static function setRealityCheck(User $user, ?int $minutes): PlayerProtection
    {
        $p = self::for($user);
        $p->reality_check_minutes = $minutes === null ? null : max(15, min(240, $minutes));
        $p->save();

        return $p;
    }

    private static function applyMaturedLimits(PlayerProtection $p): PlayerProtection
    {
        if ($p->pending_limits_effective_at === null || $p->pending_limits_effective_at->isFuture()) {
            return $p;
        }

        foreach (self::PERIODS as $period => $_) {
            $pending = $p->{"pending_deposit_limit_{$period}"};
            if ($pending !== null) {
                $p->{"deposit_limit_{$period}"} = ((string) $pending === '-1' || (float) $pending < 0) ? null : $pending;
                $p->{"pending_deposit_limit_{$period}"} = null;
            }
        }
        $p->pending_limits_effective_at = null;
        $p->save();

        return $p;
    }

    public static function present(PlayerProtection $p): array
    {
        $limits = [];
        foreach (self::PERIODS as $period => $_) {
            $pending = $p->{"pending_deposit_limit_{$period}"};
            $limits[$period] = [
                'current' => $p->{"deposit_limit_{$period}"} !== null ? Amount::normalize((string) $p->{"deposit_limit_{$period}"}) : null,
                'pending' => $pending === null ? null : (((float) $pending < 0) ? 'none' : Amount::normalize((string) $pending)),
            ];
        }

        return [
            'deposit_limits'       => $limits,
            'pending_effective_at' => $p->pending_limits_effective_at?->toIso8601String(),
            'excluded'             => $p->excluded_permanently || ($p->excluded_until?->isFuture() ?? false),
            'excluded_until'       => $p->excluded_permanently ? 'permanent' : $p->excluded_until?->toIso8601String(),
            'reality_check_minutes'=> $p->reality_check_minutes,
            'exclusion_options'    => array_keys(self::EXCLUSIONS),
        ];
    }
}
