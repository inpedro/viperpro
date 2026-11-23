<?php

namespace App\Core\Payments;

use App\Models\AffiliateWithdraw;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Withdrawal state machine.
 *
 *   PENDING (0) --approve--> PROCESSING (3) --payout ok--> PAID (1)
 *                                         \--payout failed--> PENDING (0)
 *   PENDING (0) --cancel---> CANCELLED (2)  (+ refund to the originating balance)
 *
 * Every transition is a compare-and-set UPDATE ... WHERE status = <expected>, so two
 * concurrent admin clicks (or a replayed request) can never pay out or refund twice,
 * even on storage engines without row locks.
 */
class WithdrawalService
{
    public const PENDING    = 0;
    public const PAID       = 1;
    public const CANCELLED  = 2;
    public const PROCESSING = 3;

    public const TYPE_USER      = 'user';
    public const TYPE_AFFILIATE = 'affiliate';

    /**
     * @param callable(Model):bool $payout Sends the money through the gateway. Must return true only on confirmed acceptance.
     */
    public static function approve(string $type, int|string $id, callable $payout): bool
    {
        $model = self::modelClass($type);

        if (! self::transition($model, $id, self::PENDING, self::PROCESSING)) {
            return false; // already processed, cancelled, or does not exist
        }

        $withdrawal = $model::find($id);

        try {
            $ok = (bool) $payout($withdrawal);
        } catch (\Throwable $e) {
            Log::error('withdrawal.payout.exception', ['type' => $type, 'id' => $id, 'exception' => $e]);
            $ok = false;
        }

        self::transition($model, $id, self::PROCESSING, $ok ? self::PAID : self::PENDING);

        return $ok;
    }

    public static function cancel(string $type, int|string $id): bool
    {
        $model = self::modelClass($type);

        return DB::transaction(function () use ($model, $type, $id) {
            $withdrawal = $model::find($id);
            if (! $withdrawal) {
                return false;
            }

            $wallet = Wallet::where('user_id', $withdrawal->user_id)
                ->when($withdrawal->currency, fn ($q) => $q->where('currency', $withdrawal->currency))
                ->lockForUpdate()
                ->first();
            if (! $wallet) {
                return false;
            }

            if (! self::transition($model, $id, self::PENDING, self::CANCELLED)) {
                return false;
            }

            $column = $type === self::TYPE_AFFILIATE ? 'refer_rewards' : 'balance_withdrawal';
            $wallet->increment($column, $withdrawal->amount);

            return true;
        });
    }

    /**
     * Atomically debit the player's withdrawable balance and create the withdrawal request.
     * Returns null when the balance is insufficient (checked inside the same UPDATE).
     */
    public static function request(string $type, int $userId, string $amount, array $attributes): ?Model
    {
        $amount = \App\Core\Money\Amount::normalize($amount);
        if ($amount === null || \App\Core\Money\Amount::toMinor($amount) <= 0) {
            return null;
        }

        $model  = self::modelClass($type);
        $column = $type === self::TYPE_AFFILIATE ? 'refer_rewards' : 'balance_withdrawal';

        return DB::transaction(function () use ($model, $column, $userId, $amount, $attributes) {
            // Conditional decrement: the balance check and the debit are one atomic UPDATE.
            $debited = Wallet::where('user_id', $userId)
                ->where($column, '>=', $amount)
                ->limit(1)
                ->decrement($column, $amount);

            if ($debited !== 1) {
                return null;
            }

            return $model::create(array_merge($attributes, [
                'user_id' => $userId,
                'amount'  => $amount,
                'status'  => self::PENDING,
            ]));
        });
    }

    private static function transition(string $model, int|string $id, int $from, int $to): bool
    {
        return $model::whereKey($id)->where('status', $from)->update(['status' => $to]) === 1;
    }

    /**
     * @return class-string<Model>
     */
    private static function modelClass(string $type): string
    {
        return match ($type) {
            self::TYPE_USER      => Withdrawal::class,
            self::TYPE_AFFILIATE => AffiliateWithdraw::class,
            default              => throw new \InvalidArgumentException('Unknown withdrawal type'),
        };
    }
}
