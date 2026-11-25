<?php

namespace App\Console\Commands\Addons;

use App\Core\Addons\AddonManager;
use Illuminate\Console\Command;

class AddonListCommand extends Command
{
    protected $signature = 'addon:list';
    protected $description = 'List discovered addons, their state and verification problems';

    public function handle(AddonManager $addons): int
    {
        $rows = [];
        foreach ($addons->discover(true) as $id => $m) {
            $installed = $addons->installedOrNull($id);
            $problems = $addons->check($id);
            $rows[] = [
                $id, $m->version(), $m->category(), $addons->trustLevel($id),
                $installed ? ($installed->enabled ? 'enabled' : 'installed') : 'available',
                $problems === [] ? 'ok' : implode('; ', $problems),
            ];
        }
        $this->table(['Id', 'Version', 'Category', 'Trust', 'State', 'Verification'], $rows);

        foreach ($addons->invalidManifests() as $dir => $error) {
            $this->error("{$dir}: {$error}");
        }

        return self::SUCCESS;
    }
}
