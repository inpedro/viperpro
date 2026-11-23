<?php

namespace App\Core\Addons;

use App\Core\Addons\Models\InstalledAddon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Loads enabled addons: registers their PSR-4 namespaces and service providers.
 *
 * An addon is loaded only if its addon.lock still hashes to the value recorded when it was
 * enabled (cheap per-boot tamper check). Full per-file verification: php artisan addon:verify.
 */
class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AddonManager::class);

        $manager = $this->app->make(AddonManager::class);

        foreach ($manager->enabledManifests() as $id => $manifest) {
            $recorded = InstalledAddon::find($id)?->lock_hash;
            $lockFile = $manifest->path.'/'.AddonIntegrity::LOCK;
            $current = is_file($lockFile) ? hash_file('sha256', $lockFile) : null;

            if ($recorded === null || $current === null || ! hash_equals($recorded, $current)) {
                Log::critical('addon.not_loaded.lock_changed', ['addon' => $id]);
                continue;
            }

            $manager->load($manifest);
        }
    }

    public static function registerAutoload(AddonManifest $manifest): void
    {
        $map = $manifest->psr4();
        if ($map === []) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($map): void {
            foreach ($map as $prefix => $dir) {
                if (str_starts_with($class, $prefix)) {
                    $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                    return;
                }
            }
        });
    }
}
