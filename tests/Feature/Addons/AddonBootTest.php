<?php

namespace Tests\Feature\Addons;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Regression: addons were never loaded at boot in real processes (the loader queried the DB in
 * register(), before Eloquent's connection resolver exists). In-process tests could not see it,
 * so this runs separate PHP processes against a file database, like production does.
 */
class AddonBootTest extends TestCase
{
    public function test_enabled_addons_are_loaded_in_a_fresh_process(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'addon-boot-').'.sqlite';
        touch($db);
        $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'APP_ENV' => 'testing', 'CACHE_DRIVER' => 'array'];

        try {
            Process::path(base_path())->env($env)->timeout(120)->run('php artisan migrate --force')->throw();
            Process::path(base_path())->env($env)->timeout(120)->run('php artisan addon:sync --enable')->throw();

            $loaded = Process::path(base_path())->env($env)->timeout(60)->run(
                ['php', 'artisan', 'tinker', '--execute=echo json_encode(array_keys(app(App\Core\Addons\AddonManager::class)->loaded()));']
            )->throw()->output();

            $this->assertStringContainsString('payment.stripe', $loaded);
            $this->assertStringContainsString('payment.suitpay', $loaded);
        } finally {
            @unlink($db);
        }
    }
}
