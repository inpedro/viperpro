<?php

namespace App\Core\Wallet;

use App\Core\Ledger\LedgerLeg;
use App\Core\Ledger\LedgerService;
use App\Core\Money\Amount;
use App\Core\Money\Money;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for code that still mutates wallets through the Eloquent model
 * ($wallet->increment(...), $wallet->update([...]), Filament edits).
 *
 * The exact delta is taken from the DATABASE value before and after the write (not from
 * possibly stale in-memory attributes), and journaled as source=observed against the
 * counter account given by LedgerContext (default: platform:unattributed). Writes made by
 * WalletService use query-builder UPDATEs and never reach this observer.
 *
 * Target: zero observed journals. Each remaining call site is migrated to WalletService.
 */
class WalletLedgerObserver
{
    /** @var array<int, array<string, int>> wallet id => bucket => minor (DB value before save) */
    private static array $before = [];

    public function created(Wallet $wallet): void
    {
        foreach (LedgerService::WALLET_BUCKETS as $bucket) {
            $minor = Amount::toMinor((string) ($wallet->getAttributes()[$bucket] ?? '0')) ?? 0;
            if ($minor !== 0) {
                $this->record($wallet, $bucket, 0, $minor, 'wallet.created');
            }
        }
    }

    public function updating(Wallet $wallet): void
    {
        $dirty = array_intersect(array_keys($wallet->getDirty()), LedgerService::WALLET_BUCKETS);
        if ($dirty === []) {
            return;
        }

        $row = DB::table('wallets')->where('id', $wallet->id)->first($dirty);
        foreach ($dirty as $bucket) {
            self::$before[$wallet->id][$bucket] = Amount::toMinor((string) ($row->{$bucket} ?? '0')) ?? 0;
        }
    }

    public function updated(Wallet $wallet): void
    {
        $before = self::$before[$wallet->id] ?? [];
        unset(self::$before[$wallet->id]);
        if ($before === []) {
            return;
        }

        $row = DB::table('wallets')->where('id', $wallet->id)->first(array_keys($before));
        foreach ($before as $bucket => $beforeMinor) {
            $afterMinor = Amount::toMinor((string) ($row->{$bucket} ?? '0')) ?? 0;
            if ($afterMinor !== $beforeMinor) {
                $this->record($wallet, $bucket, $beforeMinor, $afterMinor);
            }
        }
    }

    private function record(Wallet $wallet, string $bucket, int $beforeMinor, int $afterMinor, ?string $forcedType = null): void
    {
        try {
            $ctx      = LedgerContext::current();
            $currency = LedgerService::walletCurrency($wallet);
            $delta    = $afterMinor - $beforeMinor;

            if ($forcedType === null) {
                WalletService::ensureOpened($wallet, $bucket, $beforeMinor);
            }

            LedgerService::post($forcedType ?? $ctx['type'], [
                new LedgerLeg(LedgerService::walletAccount($wallet, $bucket), Money::ofMinor($delta, $currency), $afterMinor),
                new LedgerLeg(LedgerService::platformAccount($forcedType ? 'opening' : $ctx['counter'], $currency), Money::ofMinor(-$delta, $currency)),
            ], null, array_merge(['reference_type' => 'wallet', 'reference_id' => $wallet->id], $ctx['context'], [
                'source' => 'observed',
                'meta'   => array_merge($ctx['context']['meta'] ?? [], ['bucket' => $bucket, 'call_site' => self::callSite()]),
            ]));
        } catch (\Throwable $e) {
            // Never break the money movement itself; reconciliation will flag the gap.
            Log::critical('ledger.observer.failed', ['wallet' => $wallet->id, 'bucket' => $bucket, 'error' => $e->getMessage()]);
        }
    }

    /** First application frame outside the framework and this observer, for migration tracking. */
    private static function callSite(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            $file = $frame['file'] ?? '';
            if ($file !== '' && str_contains($file, '/app/') && ! str_contains($file, '/app/Core/Wallet/') && ! str_contains($file, '/app/Core/Ledger/')) {
                return str_replace(base_path().'/', '', $file).':'.($frame['line'] ?? 0);
            }
        }

        return null;
    }
}
