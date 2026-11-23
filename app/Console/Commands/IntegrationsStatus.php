<?php

namespace App\Console\Commands;

use App\Core\Games\GameProviders;
use App\Core\Payments\PaymentGateways;
use Illuminate\Console\Command;

class IntegrationsStatus extends Command
{
    protected $signature = 'integrations:status';

    protected $description = 'Show configuration/health of every payment gateway and game provider';

    public function handle(): int
    {
        $rows = [];
        foreach (PaymentGateways::all() as $g) {
            $h = $g->getHealthStatus();
            $rows[] = ['payment', $g->getIdentifier(), $h->status, implode(', ', $g->getCapabilities()), implode(' ', $h->messages)];
        }
        foreach (GameProviders::all() as $p) {
            $h = $p->getHealthStatus();
            $rows[] = ['game', $p->getIdentifier(), $h->status, implode(', ', $p->getCapabilities()), implode(' ', $h->messages)];
        }

        $this->table(['Type', 'Id', 'Status', 'Capabilities', 'Notes'], $rows);

        return self::SUCCESS;
    }
}
