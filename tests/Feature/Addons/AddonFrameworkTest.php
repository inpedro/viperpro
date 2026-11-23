<?php

namespace Tests\Feature\Addons;

use App\Core\Addons\AddonException;
use App\Core\Addons\AddonIntegrity;
use App\Core\Addons\AddonManager;
use App\Core\Addons\AddonManifest;
use App\Core\Addons\InvalidManifest;
use App\Core\Addons\Semver;
use App\Core\Payments\GatewayCredentials;
use App\Core\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Security\SecurityTestCase;

class AddonFrameworkTest extends SecurityTestCase
{
    private string $root;
    private string $thirdParty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/addons-test-'.uniqid();
        $this->thirdParty = $this->root.'-3p';
        File::ensureDirectoryExists($this->root);
        File::ensureDirectoryExists($this->thirdParty);

        config(['addons.paths' => [
            ['path' => base_path('addons'), 'trust' => 'bundled'],
            ['path' => $this->root, 'trust' => 'bundled'],
            ['path' => $this->thirdParty, 'trust' => 'third_party'],
        ]]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::deleteDirectory($this->thirdParty);
        parent::tearDown();
    }

    private function manager(): AddonManager
    {
        $m = app(AddonManager::class);
        $m->discover(true);

        return $m;
    }

    private function makeAddon(string $dir, array $manifest, array $files = [], bool $lock = true): string
    {
        $path = "{$dir}/".str_replace('.', '-', $manifest['id']);
        File::ensureDirectoryExists($path);
        file_put_contents("{$path}/addon.json", json_encode($manifest));
        foreach ($files as $name => $contents) {
            File::ensureDirectoryExists(dirname("{$path}/{$name}"));
            file_put_contents("{$path}/{$name}", $contents);
        }
        if ($lock) {
            AddonIntegrity::writeLock(AddonManifest::fromDirectory($path));
        }

        return $path;
    }

    private function base(string $id, array $extra = []): array
    {
        return array_merge(['id' => $id, 'name' => $id, 'version' => '1.0.0', 'category' => 'other', 'requires' => ['platform' => '^1.0']], $extra);
    }

    public function test_semver_constraints(): void
    {
        $this->assertTrue(Semver::satisfies('1.4.2', '^1.0'));
        $this->assertFalse(Semver::satisfies('2.0.0', '^1.0'));
        $this->assertTrue(Semver::satisfies('0.2.5', '^0.2'));
        $this->assertFalse(Semver::satisfies('0.3.0', '^0.2'));
        $this->assertTrue(Semver::satisfies('1.2.9', '~1.2.3'));
        $this->assertFalse(Semver::satisfies('1.3.0', '~1.2.3'));
        $this->assertTrue(Semver::satisfies('1.5.0', '>=1.0 <2.0'));
        $this->assertFalse(Semver::satisfies('1.0.0', 'nonsense'));
    }

    public function test_manifest_validation_rejects_unsafe_or_malformed_manifests(): void
    {
        $bad = [
            ['id' => 'NoDots', 'name' => 'x', 'version' => '1.0.0', 'category' => 'other'],
            $this->base('a.b', ['version' => '1.0']),
            $this->base('a.b', ['category' => 'casino-hacks']),
            $this->base('a.b', ['autoload' => ['psr-4' => ['A\\' => '../../app/']]]),
            $this->base('a.b', ['migrations' => '../database']),
            $this->base('a.b', ['config' => [['key' => 'k', 'label' => 'K', 'type' => 'string'], ['key' => 'k', 'label' => 'K2', 'type' => 'string']]]),
        ];

        foreach ($bad as $i => $manifest) {
            try {
                AddonManifest::fromArray($manifest);
                $this->fail("manifest #{$i} should be rejected");
            } catch (InvalidManifest) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_tampered_or_unlocked_addons_cannot_be_installed(): void
    {
        $path = $this->makeAddon($this->root, $this->base('test.tamper'), ['src/A.php' => '<?php // v1']);
        file_put_contents("{$path}/src/A.php", '<?php // injected');
        file_put_contents("{$path}/src/B.php", '<?php // extra');
        $this->makeAddon($this->root, $this->base('test.nolock'), [], false);

        $problems = $this->manager()->check('test.tamper');
        $this->assertContains('modified file src/A.php', $problems);
        $this->assertContains('unexpected file src/B.php', $problems);
        $this->assertNotEmpty($this->manager()->check('test.nolock'));

        $this->expectException(AddonException::class);
        $this->manager()->install('test.tamper');
    }

    public function test_third_party_addons_require_a_trusted_signature(): void
    {
        $path = $this->makeAddon($this->thirdParty, $this->base('vendor.signed'));
        $manifest = AddonManifest::fromDirectory($path);

        $this->assertContains('addon.sig is missing and signatures are required', $this->manager()->check('vendor.signed'));

        $untrusted = AddonIntegrity::generateKeyPair();
        AddonIntegrity::sign($manifest, 'other', $untrusted['secret']);
        $this->assertStringContainsString('untrusted key', implode(' ', $this->manager()->check('vendor.signed')));

        $trusted = AddonIntegrity::generateKeyPair();
        config(['addons.trusted_keys' => ['release' => $trusted['public']]]);
        AddonIntegrity::sign($manifest, 'release', $trusted['secret']);
        $this->assertSame([], $this->manager()->check('vendor.signed'));

        // Re-locking after a change (without re-signing) breaks the signature.
        file_put_contents("{$path}/new.php", '<?php');
        AddonIntegrity::writeLock($manifest);
        $this->assertContains('addon.sig signature is invalid', $this->manager()->check('vendor.signed'));
    }

    public function test_platform_and_dependency_constraints(): void
    {
        $this->makeAddon($this->root, $this->base('test.future', ['requires' => ['platform' => '^2.0']]));
        $this->makeAddon($this->root, $this->base('test.base', ['version' => '1.2.0']));
        $this->makeAddon($this->root, $this->base('test.child', ['requires' => ['platform' => '^1.0', 'addons' => ['test.base' => '^1.1']]]));
        $m = $this->manager();

        $this->assertStringContainsString('requires platform ^2.0', implode(' ', $m->check('test.future')));
        $this->assertStringContainsString('requires addon test.base', implode(' ', $m->check('test.child')));

        $m->install('test.base');
        $m->enable('test.base');
        $m->install('test.child');
        $m->enable('test.child');

        $this->expectException(AddonException::class);
        $m->disable('test.base'); // still required by test.child
    }

    public function test_install_runs_migrations_registers_permissions_and_uninstall_keeps_data(): void
    {
        $migration = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('addon_test_rows', fn (Blueprint $t) => $t->id()); }
    public function down(): void { Schema::dropIfExists('addon_test_rows'); }
};
PHP;
        $this->makeAddon($this->root, $this->base('test.data', ['migrations' => 'migrations', 'permissions' => ['test.data.view']]),
            ['migrations/2026_01_01_000000_create_addon_test_rows.php' => $migration]);
        $m = $this->manager();

        $m->install('test.data');
        $this->assertTrue(Schema::hasTable('addon_test_rows'));
        $this->assertTrue(Permission::where('name', 'test.data.view')->exists());

        $m->uninstall('test.data');
        $this->assertTrue(Schema::hasTable('addon_test_rows'), 'uninstall must not delete data without --purge');
        $this->assertSame(['addon.installed', 'addon.uninstalled'],
            DB::table('audit_logs')->where('subject_id', 'test.data')->orderBy('id')->pluck('action')->all());
    }

    public function test_configuration_is_validated_encrypted_and_masked(): void
    {
        $this->makeAddon($this->root, $this->base('test.cfg', ['config' => [
            ['key' => 'endpoint', 'label' => 'Endpoint', 'type' => 'url', 'required' => true],
            ['key' => 'api_secret', 'label' => 'Secret', 'type' => 'string', 'secret' => true, 'required' => true],
        ]]));
        $m = $this->manager();
        $m->install('test.cfg');

        $this->assertNotEmpty($m->configure('test.cfg', ['endpoint' => 'http://insecure.test', 'api_secret' => 's3cr3t']));
        $this->assertSame([], $m->configure('test.cfg', ['endpoint' => 'https://api.test', 'api_secret' => 's3cr3t-value']));

        $raw = (string) DB::table('addons')->where('id', 'test.cfg')->value('config');
        $this->assertStringNotContainsString('s3cr3t-value', $raw);
        $this->assertSame('********', $m->maskedConfig('test.cfg')['api_secret']);

        // Leaving a secret blank keeps it.
        $this->assertSame([], $m->configure('test.cfg', ['endpoint' => 'https://api2.test', 'api_secret' => '']));
        $this->assertSame('s3cr3t-value', $m->config('test.cfg', 'api_secret'));
        $this->assertStringNotContainsString('s3cr3t', (string) DB::table('audit_logs')->where('action', 'addon.configured')->pluck('meta')->implode(' '));
    }

    public function test_payment_gateways_come_from_enabled_addons_only(): void
    {
        $m = $this->manager();
        $this->assertTrue(PaymentGateways::has('suitpay'));

        $m->disable('payment.suitpay');
        $this->assertFalse(PaymentGateways::has('suitpay'));
        $this->assertTrue(PaymentGateways::has('mercadopago'));
    }

    public function test_addon_config_overrides_legacy_plaintext_columns(): void
    {
        DB::table('gateways')->insert(['mp_access_token' => 'legacy-token']);
        $this->assertSame('legacy-token', GatewayCredentials::get('payment.mercadopago', 'access_token', 'mp_access_token'));

        $this->manager()->configure('payment.mercadopago', ['access_token' => 'addon-token']);
        $this->assertSame('addon-token', GatewayCredentials::get('payment.mercadopago', 'access_token', 'mp_access_token'));
    }
}
