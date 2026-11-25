<?php

namespace App\Console\Commands;

use App\Core\Ledger\Reconciliation;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use Illuminate\Console\Command;

/**
 * Compares every wallet bucket (the projection) with the sum of its ledger entries, and
 * checks that every journal balances to zero per currency. Each casino's result is stored
 * (reconciliation_runs) and shown in Admin → Reconciliation.
 *
 *   php artisan wallet:reconcile           report only (exit 1 on any mismatch)
 *   php artisan wallet:reconcile --open    first post opening balances for never-touched buckets
 */
class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile {--open : Post opening balances for buckets that have no ledger entries yet} {--trigger=cli : recorded with the run (cli|schedule)}';

    protected $description = 'Reconcile wallet balances against the ledger';

    public function handle(): int
    {
        $checked = $mismatches = $unbalanced = $observed = 0;
        $trigger = in_array($this->option('trigger'), ['cli', 'schedule'], true) ? $this->option('trigger') : 'cli';

        foreach (Tenant::orderBy('id')->get() as $tenant) {
            $run = Tenancy::runAs($tenant, fn () => Reconciliation::run($trigger, (bool) $this->option('open'), function (array $row) use ($tenant) {
                $this->error(sprintf('[tenant %d] wallet %d %-18s projection %s  ledger %s  diff %s',
                    $tenant->id, $row['wallet_id'], $row['bucket'], $row['projection'], $row['ledger'], $row['difference']));
            }));
            $checked += $run->buckets_checked;
            $mismatches += $run->mismatches;
            $unbalanced += $run->unbalanced_journals;
            $observed += $run->observed_journals;
        }

        $this->line("Checked {$checked} wallet buckets: {$mismatches} mismatches, {$unbalanced} unbalanced journals.");
        $this->line("Journals recorded by the legacy observer (call sites still to migrate): {$observed}.");

        return ($mismatches === 0 && $unbalanced === 0) ? self::SUCCESS : self::FAILURE;
    }
}
