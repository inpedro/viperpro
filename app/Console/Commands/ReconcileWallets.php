<?php

namespace App\Console\Commands;

use App\Core\Ledger\LedgerService;
use App\Core\Ledger\Models\LedgerAccount;
use App\Core\Money\Amount;
use App\Core\Wallet\WalletService;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Compares every wallet bucket (the projection) with the sum of its ledger entries, and
 * checks that every journal balances to zero per currency.
 *
 *   php artisan wallet:reconcile           report only (exit 1 on any mismatch)
 *   php artisan wallet:reconcile --open    first post opening balances for never-touched buckets
 */
class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile {--open : Post opening balances for buckets that have no ledger entries yet}';

    protected $description = 'Reconcile wallet balances against the ledger';

    public function handle(): int
    {
        $mismatches = 0;
        $checked = 0;

        Wallet::query()->orderBy('id')->chunkById(500, function ($wallets) use (&$mismatches, &$checked) {
            foreach ($wallets as $wallet) {
                foreach (LedgerService::WALLET_BUCKETS as $bucket) {
                    $projection = WalletService::bucketMinor($wallet, $bucket);

                    if ($this->option('open')) {
                        DB::transaction(function () use ($wallet, $bucket) {
                            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
                            WalletService::ensureOpened($locked, $bucket, WalletService::bucketMinor($locked, $bucket));
                        });
                    }

                    $account = LedgerAccount::where('code', "wallet:{$wallet->id}:{$bucket}")->first();
                    $ledger  = $account ? LedgerService::accountBalanceMinor($account) : 0;
                    $checked++;

                    if ($ledger !== $projection) {
                        $mismatches++;
                        $this->error(sprintf(
                            'wallet %d %-18s projection %s  ledger %s  diff %s',
                            $wallet->id, $bucket, Amount::fromMinor($projection), Amount::fromMinor($ledger), Amount::fromMinor($projection - $ledger)
                        ));
                    }
                }
            }
        });

        $unbalanced = DB::table('ledger_entries')
            ->select('journal_id', 'currency', DB::raw('SUM(amount_minor) AS total'))
            ->groupBy('journal_id', 'currency')
            ->havingRaw('SUM(amount_minor) <> 0')
            ->count();

        $observed = DB::table('ledger_journals')->where('source', 'observed')->count();

        $this->line("Checked {$checked} wallet buckets: {$mismatches} mismatches, {$unbalanced} unbalanced journals.");
        $this->line("Journals recorded by the legacy observer (call sites still to migrate): {$observed}.");

        return ($mismatches === 0 && $unbalanced === 0) ? self::SUCCESS : self::FAILURE;
    }
}
