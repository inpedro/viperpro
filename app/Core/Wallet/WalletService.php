<?php

namespace App\Core\Wallet;

use App\Core\Ledger\LedgerLeg;
use App\Core\Ledger\LedgerService;
use App\Core\Money\Amount;
use App\Core\Money\Money;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * The single, explicit way to move money in or out of a wallet bucket.
 *
 * Every operation, inside one DB transaction:
 *   1. locks the wallet row (SELECT ... FOR UPDATE),
 *   2. short-circuits if the idempotency key was already used,
 *   3. checks funds for debits,
 *   4. writes the projection column with a query-builder UPDATE (so the legacy
 *      WalletLedgerObserver does not record it a second time),
 *   5. posts a balanced journal: wallet bucket <-> platform counter account.
 */
class WalletService
{
    /**
     * @return WalletResult ok=false with reason 'insufficient_funds' when a debit cannot be covered.
     */
    public static function credit(Wallet|int $wallet, string $bucket, Money|string $amount, string $type, string $counter, ?string $idempotencyKey = null, array $context = []): WalletResult
    {
        return self::apply($wallet, [[$bucket, $amount, +1]], $type, $counter, $idempotencyKey, $context);
    }

    public static function debit(Wallet|int $wallet, string $bucket, Money|string $amount, string $type, string $counter, ?string $idempotencyKey = null, array $context = []): WalletResult
    {
        return self::apply($wallet, [[$bucket, $amount, -1]], $type, $counter, $idempotencyKey, $context);
    }

    /**
     * Move funds between two buckets of the same wallet (e.g. rollover completion:
     * balance -> balance_withdrawal). Nets to zero on the platform side.
     */
    public static function transfer(Wallet|int $wallet, string $from, string $to, Money|string $amount, string $type, ?string $idempotencyKey = null, array $context = []): WalletResult
    {
        return self::apply($wallet, [[$from, $amount, -1], [$to, $amount, +1]], $type, null, $idempotencyKey, $context);
    }

    /**
     * Debit a bet using the platform's bucket priority (preserves Helper::DiscountBalance semantics):
     *   real balance -> withdrawable balance -> real+withdrawable combined -> bonus (only when
     *   no real money is left). Returns the bucket the bet was charged to, or null if not coverable.
     */
    public static function debitBet(Wallet|int $wallet, Money|string $bet, ?string $idempotencyKey = null, array $context = []): WalletResult
    {
        return DB::transaction(function () use ($wallet, $bet, $idempotencyKey, $context) {
            $locked = self::lock($wallet);
            $bet    = self::money($locked, $bet);

            $real       = self::bucketMinor($locked, 'balance');
            $withdrawal = self::bucketMinor($locked, 'balance_withdrawal');
            $bonus      = self::bucketMinor($locked, 'balance_bonus');

            if ($real >= $bet->minor) {
                $plan = [['balance', $bet->minor]];
                $charged = 'balance';
            } elseif ($withdrawal >= $bet->minor) {
                $plan = [['balance_withdrawal', $bet->minor]];
                $charged = 'balance_withdrawal';
            } elseif ($real + $withdrawal >= $bet->minor) {
                $plan = array_values(array_filter([
                    ['balance', $real],
                    ['balance_withdrawal', $bet->minor - $real],
                ], fn ($p) => $p[1] > 0));
                $charged = 'balance_withdrawal';
            } elseif ($real <= 0 && $withdrawal <= 0 && $bonus >= $bet->minor) {
                $plan = [['balance_bonus', $bet->minor]];
                $charged = 'balance_bonus';
            } else {
                return WalletResult::failed('insufficient_funds', $locked);
            }

            $changes = array_map(fn ($p) => [$p[0], Money::ofMinor($p[1], $bet->currency), -1], $plan);
            $result  = self::applyLocked($locked, $changes, 'game.bet', 'game', $idempotencyKey, $context);

            return $result->ok ? $result->withBucket($charged) : $result;
        });
    }

    /**
     * @param array<int, array{0: string, 1: Money|string, 2: int}> $changes [bucket, amount, sign]
     */
    private static function apply(Wallet|int $wallet, array $changes, string $type, ?string $counter, ?string $idempotencyKey, array $context): WalletResult
    {
        return DB::transaction(function () use ($wallet, $changes, $type, $counter, $idempotencyKey, $context) {
            $locked = self::lock($wallet);
            $changes = array_map(fn ($c) => [$c[0], self::money($locked, $c[1]), $c[2]], $changes);

            return self::applyLocked($locked, $changes, $type, $counter, $idempotencyKey, $context);
        });
    }

    private static function applyLocked(Wallet $locked, array $changes, string $type, ?string $counter, ?string $idempotencyKey, array $context): WalletResult
    {
        if ($idempotencyKey !== null && \App\Core\Ledger\Models\LedgerJournal::where('idempotency_key', $idempotencyKey)->exists()) {
            return WalletResult::duplicate($locked);
        }

        $currency = LedgerService::walletCurrency($locked);
        $newValues = [];
        $legs = [];
        $platformTotal = 0;

        foreach ($changes as [$bucket, $money, $sign]) {
            /** @var Money $money */
            if (! $money->isPositive()) {
                throw new \InvalidArgumentException('Wallet operations take positive amounts; the operation decides the sign.');
            }
            if ($money->currency !== $currency) {
                throw new \InvalidArgumentException("Wallet {$locked->id} is {$currency}, got {$money->currency}");
            }

            $before = $newValues[$bucket] ?? self::bucketMinor($locked, $bucket);
            $after  = $before + $sign * $money->minor;

            if ($after < 0) {
                return WalletResult::failed('insufficient_funds', $locked);
            }

            self::ensureOpened($locked, $bucket, $before);

            $newValues[$bucket] = $after;
            $legs[] = new LedgerLeg(LedgerService::walletAccount($locked, $bucket), Money::ofMinor($sign * $money->minor, $currency), $after);
            $platformTotal -= $sign * $money->minor;
        }

        if ($platformTotal !== 0) {
            if ($counter === null) {
                throw new \LogicException("Operation [{$type}] does not balance and has no counter account");
            }
            $legs[] = new LedgerLeg(LedgerService::platformAccount($counter, $currency), Money::ofMinor($platformTotal, $currency));
        }

        Wallet::whereKey($locked->id)->update(array_map(fn ($minor) => Amount::fromMinor($minor), $newValues));

        [$journal] = LedgerService::post($type, $legs, $idempotencyKey, $context);

        foreach ($newValues as $bucket => $minor) {
            $locked->setAttribute($bucket, Amount::fromMinor($minor));
        }
        $locked->syncOriginal();

        return WalletResult::success($locked, $journal);
    }

    /**
     * Pre-ledger balances: the first time a bucket is touched, post its current value as an
     * opening balance so the ledger always reconciles with the projection.
     */
    public static function ensureOpened(Wallet $wallet, string $bucket, int $currentMinor): void
    {
        $account = LedgerService::walletAccount($wallet, $bucket);
        if ($currentMinor === 0 || $account->entries()->exists()) {
            return;
        }

        $currency = $account->currency;
        LedgerService::post('opening_balance', [
            new LedgerLeg($account, Money::ofMinor($currentMinor, $currency), $currentMinor),
            new LedgerLeg(LedgerService::platformAccount('opening', $currency), Money::ofMinor(-$currentMinor, $currency)),
        ], "opening:{$wallet->id}:{$bucket}", ['reference_type' => 'wallet', 'reference_id' => $wallet->id, 'source' => 'service']);
    }

    public static function bucketMinor(Wallet $wallet, string $bucket): int
    {
        $minor = Amount::toMinor((string) ($wallet->getAttributes()[$bucket] ?? '0'));
        if ($minor === null) {
            throw new \UnexpectedValueException("Wallet {$wallet->id}.{$bucket} has a non-decimal value");
        }

        return $minor;
    }

    private static function lock(Wallet|int $wallet): Wallet
    {
        $id = $wallet instanceof Wallet ? $wallet->id : $wallet;

        return Wallet::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function money(Wallet $wallet, Money|string $amount): Money
    {
        return $amount instanceof Money ? $amount : Money::of($amount, LedgerService::walletCurrency($wallet));
    }
}
