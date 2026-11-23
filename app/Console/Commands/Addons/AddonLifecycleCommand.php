<?php

namespace App\Console\Commands\Addons;

use App\Core\Addons\AddonException;
use App\Core\Addons\AddonManager;
use Illuminate\Console\Command;

class AddonLifecycleCommand extends Command
{
    protected $signature = 'addon {action : install|enable|disable|update|uninstall} {id} {--enable : enable after install} {--purge : on uninstall, roll back the addon migrations (deletes its data)}';
    protected $description = 'Install, enable, disable, update or uninstall an addon';

    public function handle(AddonManager $addons): int
    {
        $id = (string) $this->argument('id');

        try {
            match ($this->argument('action')) {
                'install'   => $addons->install($id) && ($this->option('enable') ? $addons->enable($id) : null),
                'enable'    => $addons->enable($id),
                'disable'   => $addons->disable($id),
                'update'    => $addons->update($id),
                'uninstall' => $this->option('purge') && ! $this->confirm("Purge will roll back {$id}'s migrations and DELETE its data. Continue?")
                    ? throw new AddonException('Aborted')
                    : $addons->uninstall($id, (bool) $this->option('purge')),
                default     => throw new AddonException('Unknown action'),
            };
        } catch (AddonException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("{$this->argument('action')}: {$id} done.");

        return self::SUCCESS;
    }
}
