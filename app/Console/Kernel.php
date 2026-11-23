<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Financial integrity: projection vs ledger, journal balance. Non-zero exit = alert.
        $schedule->command('wallet:reconcile')->hourly()->withoutOverlapping();

        // Full per-file integrity/signature verification of every addon on disk.
        $schedule->command('addon:package verify')->hourly()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
