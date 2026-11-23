<?php

namespace App\Console\Commands\Addons;

use App\Core\Addons\AddonException;
use App\Core\Addons\AddonManager;
use Illuminate\Console\Command;

/**
 * Deployment step: install (and optionally enable) every *bundled* addon that is not
 * installed yet and passes verification; update installed ones whose version changed.
 */
class AddonSyncCommand extends Command
{
    protected $signature = 'addon:sync {--enable : enable newly installed bundled addons}';
    protected $description = 'Install/update bundled addons after a deployment';

    public function handle(AddonManager $addons): int
    {
        $failed = false;

        foreach ($addons->discover(true) as $id => $manifest) {
            if ($addons->trustLevel($id) !== 'bundled') {
                continue;
            }

            try {
                $installed = $addons->installedOrNull($id);
                if (! $installed) {
                    $addons->install($id);
                    if ($this->option('enable')) {
                        $addons->enable($id);
                    }
                    $this->info("installed {$id} {$manifest->version()}".($this->option('enable') ? ' (enabled)' : ''));
                } elseif ($installed->version !== $manifest->version()) {
                    $addons->update($id);
                    $this->info("updated {$id} {$installed->version} -> {$manifest->version()}");
                }
            } catch (AddonException $e) {
                $failed = true;
                $this->error($e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
