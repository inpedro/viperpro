<?php

namespace App\Core\Ledger;

use App\Core\Ledger\Models\LedgerAccount;
use App\Core\Ledger\Models\LedgerJournal;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LedgerService
{
    /** Money-bearing wallet columns that are mirrored in the ledger. */
    public const WALLET_BUCKETS = ['balance', 'balance_bonus', 'balance_withdrawal', 'refer_rewards'];

    public static function walletAccount(Wallet $wallet, string $bucket): LedgerAccount
    {
        if (! in_array($bucket, self::WALLET_BUCKETS, true)) {
            throw new InvalidArgumentException("Bucket [{$bucket}] is not a ledger bucket");
        }

        $currency = self::walletCurrency($wallet);

        return LedgerAccount::firstOrCreate(
            ['code' => "wallet:{$wallet->id}:{$bucket}"],
            ['kind' => 'player', 'wallet_id' => $wallet->id, 'bucket' => $bucket, 'currency' => $currency],
        );
    }

    /**
     * Platform-side counter accounts, one per purpose and currency, e.g.
     * deposits (money received from gateways), withdrawals, game (house), bonus (expense),
     * affiliate (expense), adjustments (manual), opening (pre-ledger balances).
     */
    public static function platformAccount(string $name, string $currency): LedgerAccount
    {
        $currency = strtoupper($currency);

        return LedgerAccount::firstOrCreate(
            ['code' => "platform:{$name}:{$currency}"],
            ['kind' => 'platform', 'currency' => $currency],
        );
    }

    public static function walletCurrency(Wallet $wallet): string
    {
        $currency = strtoupper((string) ($wallet->currency ?: \App\Helpers\Core::getSetting()->currency_code ?? ''));
        if ($currency === '') {
            throw new InvalidArgumentException("Wallet {$wallet->id} has no currency");
        }

        return $currency;
    }

    /**
     * Post a balanced journal. Returns [journal, created]. If the idempotency key was already
     * used, the existing journal is returned and nothing is written (created = false).
     *
     * @param LedgerLeg[] $legs
     * @return array{0: LedgerJournal, 1: bool}
     */
    public static function post(string $type, array $legs, ?string $idempotencyKey = null, array $context = []): array
    {
        if (count($legs) < 2) {
            throw new InvalidArgumentException('A journal needs at least two legs');
        }

        $sums = [];
        foreach ($legs as $leg) {
            $sums[$leg->amount->currency] = ($sums[$leg->amount->currency] ?? 0) + $leg->amount->minor;
        }
        foreach ($sums as $currency => $sum) {
            if ($sum !== 0) {
                throw new InvalidArgumentException("Unbalanced journal [{$type}] in {$currency}: {$sum}");
            }
        }

        return DB::transaction(function () use ($type, $legs, $idempotencyKey, $context) {
            if ($idempotencyKey !== null) {
                $existing = LedgerJournal::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return [$existing, false];
                }
            }

            $journal = LedgerJournal::create([
                'uuid'            => (string) Str::uuid(),
                'type'            => $type,
                'idempotency_key' => $idempotencyKey,
                'reference_type'  => $context['reference_type'] ?? null,
                'reference_id'    => isset($context['reference_id']) ? (string) $context['reference_id'] : null,
                'actor_id'        => $context['actor_id'] ?? (auth()->check() ? auth()->id() : null),
                'source'          => $context['source'] ?? 'service',
                'description'     => $context['description'] ?? null,
                'meta'            => $context['meta'] ?? null,
            ]);

            foreach ($legs as $leg) {
                $journal->entries()->create([
                    'account_id'          => $leg->account->id,
                    'amount_minor'        => $leg->amount->minor,
                    'currency'            => $leg->amount->currency,
                    'balance_after_minor' => $leg->balanceAfterMinor,
                ]);
            }

            return [$journal, true];
        });
    }

    public static function accountBalanceMinor(LedgerAccount $account): int
    {
        return (int) $account->entries()->sum('amount_minor');
    }
}
