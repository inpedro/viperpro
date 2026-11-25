<?php

namespace App\Console\Commands;

use App\Core\Games\GameProviders;
use App\Core\Payments\PaymentGateways;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Health of every payment gateway and game provider, per tenant.
 *
 *   php artisan integrations:status              table for the default tenant
 *   php artisan integrations:status --all        every tenant (scheduled hourly); warnings are
 *                                                logged for active integrations that are unusable
 */
class IntegrationsStatus extends Command
{
    protected $signature = 'integrations:status {--all : check every tenant and log problems}';

    protected $description = 'Show configuration/health of payment gateways and game providers';

    public function handle(): int
    {
        $tenants = $this->option('all') ? Tenant::orderBy('id')->get() : collect([Tenancy::current()])->filter();
        $problems = 0;

        foreach ($tenants as $tenant) {
            Tenancy::runAs($tenant, function () use ($tenant, &$problems) {
                $rows = [];
                foreach (PaymentGateways::all() as $g) {
                    $h = $g->getHealthStatus();
                    $rows[] = ['payment', $g->getIdentifier(), $h->status, implode(', ', $g->getCapabilities()), implode(' ', $h->messages)];
                    // Activated for this casino but not usable: players will see the method fail.
                    if (! $h->isUsable()) {
                        $problems++;
                        Log::warning('integrations.unusable', ['tenant' => $tenant->id, 'gateway' => $g->getIdentifier(), 'status' => $h->status, 'messages' => $h->messages]);
                    }
                }
                foreach (GameProviders::all() as $p) {
                    $h = $p->getHealthStatus();
                    $rows[] = ['game', $p->getIdentifier(), $h->status, implode(', ', $p->getCapabilities()), implode(' ', $h->messages)];
                }

                $this->info("Tenant {$tenant->id} ({$tenant->name})");
                $this->table(['Type', 'Id', 'Status', 'Capabilities', 'Notes'], $rows);
            });
        }

        if ($this->option('all')) {
            $this->line("{$problems} active payment integrations are not usable.");
        }

        return self::SUCCESS;
    }
}
