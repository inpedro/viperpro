<?php

namespace App\Core\Ledger;

use App\Core\Ledger\Models\LedgerAccount;
use App\Core\Money\Amount;
use App\Core\Tenancy\Tenancy;
use App\Core\Wallet\WalletService;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compares every wallet bucket of the current casino (the projection) with the sum of its ledger
 * entries, and counts journals that don't balance to zero. Read-only unless $open is true, which
 * first posts opening balances for buckets that have never been journaled.
 */
final class Reconciliation
{
    public const MAX_DETAILS = 200;

    public static function run(string $trigger, bool $open = false, ?callable $onMismatch = null): ReconciliationRun
    {
        $tid = Tenancy::requireId();
        $run = ReconciliationRun::create(['trigger' => $trigger, 'started_at' => now()]);
        $checked = 0;
        $mismatches = 0;
        $details = [];

        Wallet::query()->orderBy('id')->chunkById(500, function ($wallets) use ($open, $onMismatch, &$checked, &$mismatches, &$details) {
            foreach ($wallets as $wallet) {
                foreach (LedgerService::WALLET_BUCKETS as $bucket) {
                    if ($open) {
                        DB::transaction(function () use ($wallet, $bucket) {
                            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
                            WalletService::ensureOpened($locked, $bucket, WalletService::bucketMinor($locked, $bucket));
                        });
                        $wallet->refresh();
                    }

                    $projection = WalletService::bucketMinor($wallet, $bucket);
                    $account = LedgerAccount::where('code', "wallet:{$wallet->id}:{$bucket}")->first();
                    $ledger = $account ? LedgerService::accountBalanceMinor($account) : 0;
                    $checked++;

                    if ($ledger !== $projection) {
                        $mismatches++;
                        $row = [
                            'wallet_id' => $wallet->id, 'user_id' => $wallet->user_id, 'bucket' => $bucket,
                            'projection' => Amount::fromMinor($projection), 'ledger' => Amount::fromMinor($ledger),
                            'difference' => Amount::fromMinor($projection - $ledger),
                        ];
                        if (count($details) < self::MAX_DETAILS) {
                            $details[] = $row;
                        }
                        $onMismatch && $onMismatch($row);
                    }
                }
            }
        });

        $unbalanced = DB::table('ledger_entries')->where('tenant_id', $tid)
            ->select('journal_id', 'currency')->groupBy('journal_id', 'currency')
            ->havingRaw('SUM(amount_minor) <> 0')->get()->count();
        $observed = DB::table('ledger_journals')->where('tenant_id', $tid)->where('source', 'observed')->count();

        $run->update([
            'buckets_checked' => $checked, 'mismatches' => $mismatches, 'unbalanced_journals' => $unbalanced,
            'observed_journals' => $observed, 'details' => $details, 'finished_at' => now(),
        ]);

        if (! $run->passed()) {
            Log::warning('wallet.reconciliation_failed', ['tenant' => $tid, 'run' => $run->id, 'mismatches' => $mismatches, 'unbalanced' => $unbalanced]);
        }

        return $run;
    }
}
