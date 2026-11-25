<?php

namespace App\Core\Addons;

use App\Core\Addons\Models\InstalledAddon;
use App\Core\Addons\Models\TenantAddon;
use App\Core\Tenancy\Tenancy;
use App\Core\Audit\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Addon lifecycle: discover -> install -> enable <-> disable -> uninstall.
 *
 * Every state change re-verifies package integrity (addon.lock / addon.sig), the platform
 * version constraint and dependencies, and is written to audit_logs.
 */
class AddonManager
{
    /** @var array<string, AddonManifest>|null */
    private ?array $discovered = null;

    /** @var array<string, string> path => error */
    private array $invalid = [];

    /** @var array<string, string> id => trust level */
    private array $trust = [];

    /** @var array<string, AddonManifest> addons whose code is loaded in this process */
    private array $loaded = [];

    public function markLoaded(AddonManifest $manifest): void
    {
        $this->loaded[$manifest->id()] = $manifest;
    }

    /** @return array<string, AddonManifest> */
    public function loaded(): array
    {
        return $this->loaded;
    }

    /** Load an enabled addon into the running process (autoload + providers). */
    public function load(AddonManifest $manifest): void
    {
        if (isset($this->loaded[$manifest->id()])) {
            return;
        }

        AddonServiceProvider::registerAutoload($manifest);
        foreach ($manifest->providers() as $provider) {
            app()->register($provider);
        }
        $this->markLoaded($manifest);
    }

    /** @return array<string, class-string> gateway id => adapter class, from loaded addons active for this tenant */
    public function contributedPaymentGateways(): array
    {
        $out = [];
        foreach ($this->loaded as $id => $manifest) {
            if ($this->isActiveForTenant($id)) {
                $out += $manifest->paymentGateways();
            }
        }

        return $out;
    }

    // ---- per-tenant activation ------------------------------------------------------------

    public function isActiveForTenant(string $id): bool
    {
        if (! Tenancy::id()) {
            return false; // platform context: tenant integrations are not in play
        }

        try {
            return (bool) TenantAddon::where('addon_id', $id)->value('enabled');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Activate a platform-enabled addon for the current tenant. */
    public function activateForTenant(string $id): void
    {
        Tenancy::requireId();

        if (! $this->isEnabled($id)) {
            throw new AddonException("Addon [{$id}] is not enabled on this platform");
        }

        foreach (array_keys($this->manifest($id)->addonDependencies()) as $dep) {
            if (! $this->isActiveForTenant($dep)) {
                throw new AddonException("Activate [{$dep}] first: [{$id}] depends on it");
            }
        }

        TenantAddon::updateOrCreate(['addon_id' => $id], ['enabled' => true]);
        Audit::record('addon.tenant_activated', 'addon', $id);
    }

    public function deactivateForTenant(string $id): void
    {
        Tenancy::requireId();

        $dependents = array_keys(array_filter(
            $this->enabledManifests(),
            fn (AddonManifest $m, string $other) => array_key_exists($id, $m->addonDependencies()) && $this->isActiveForTenant($other),
            ARRAY_FILTER_USE_BOTH
        ));
        if ($dependents !== []) {
            throw new AddonException("Cannot deactivate [{$id}]: required by ".implode(', ', $dependents));
        }

        TenantAddon::where('addon_id', $id)->update(['enabled' => false]);
        Audit::record('addon.tenant_deactivated', 'addon', $id);
    }

    private function tenantRecord(string $id): ?TenantAddon
    {
        try {
            return Tenancy::id() ? TenantAddon::where('addon_id', $id)->first() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, AddonManifest> */
    public function discover(bool $fresh = false): array
    {
        if ($this->discovered !== null && ! $fresh) {
            return $this->discovered;
        }

        $this->discovered = [];
        $this->invalid = [];

        foreach ((array) config('addons.paths', []) as $source) {
            $root = (string) ($source['path'] ?? '');
            if ($root === '' || ! is_dir($root)) {
                continue;
            }

            foreach (glob(rtrim($root, '/').'/*/addon.json') ?: [] as $file) {
                $dir = dirname($file);
                try {
                    $manifest = AddonManifest::fromDirectory($dir);
                    if (isset($this->discovered[$manifest->id()])) {
                        throw new InvalidManifest("Duplicate addon id [{$manifest->id()}]");
                    }
                    $this->discovered[$manifest->id()] = $manifest;
                    $this->trust[$manifest->id()] = ($source['trust'] ?? 'third_party') === 'bundled' ? 'bundled' : 'third_party';
                } catch (InvalidManifest $e) {
                    $this->invalid[$dir] = $e->getMessage();
                }
            }
        }

        ksort($this->discovered);

        return $this->discovered;
    }

    /** @return array<string, string> */
    public function invalidManifests(): array
    {
        $this->discover();

        return $this->invalid;
    }

    public function manifest(string $id): AddonManifest
    {
        return $this->discover()[$id] ?? throw new AddonException("Addon [{$id}] not found in any addon path");
    }

    public function trustLevel(string $id): string
    {
        $this->discover();

        return $this->trust[$id] ?? 'third_party';
    }

    public function requiresSignature(string $id): bool
    {
        return $this->trustLevel($id) === 'third_party' || (bool) config('addons.require_signature_bundled', false);
    }

    /** @return string[] problems preventing installation/enabling */
    public function check(string $id): array
    {
        $manifest = $this->manifest($id);
        $problems = AddonIntegrity::verify($manifest, $this->requiresSignature($id));

        $platform = (string) config('addons.platform_version');
        if (! Semver::satisfies($platform, $manifest->platformConstraint())) {
            $problems[] = "requires platform {$manifest->platformConstraint()}, this is {$platform}";
        }

        foreach ($manifest->addonDependencies() as $dep => $constraint) {
            $installed = InstalledAddon::find($dep);
            if (! $installed || ! $installed->enabled) {
                $problems[] = "requires addon {$dep} ({$constraint}) to be installed and enabled";
            } elseif (! Semver::satisfies($installed->version, $constraint)) {
                $problems[] = "requires {$dep} {$constraint}, installed {$installed->version}";
            }
        }

        return $problems;
    }

    public function install(string $id): InstalledAddon
    {
        if (InstalledAddon::find($id)) {
            throw new AddonException("Addon [{$id}] is already installed");
        }

        $manifest = $this->manifest($id);
        $this->assertNoProblems($id);

        if ($path = $manifest->migrationsPath()) {
            if (is_dir($path)) {
                Artisan::call('migrate', ['--path' => $path, '--realpath' => true, '--force' => true]);
            }
        }

        foreach ($manifest->permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $addon = InstalledAddon::create([
            'id'        => $id,
            'version'   => $manifest->version(),
            'path'      => $manifest->path,
            'enabled'   => false,
            'config'    => [],
            'lock_hash' => hash_file('sha256', $manifest->path.'/'.AddonIntegrity::LOCK),
        ]);

        Audit::record('addon.installed', 'addon', $id, ['version' => $manifest->version(), 'trust' => $this->trustLevel($id)]);

        return $addon;
    }

    public function enable(string $id): void
    {
        $addon = $this->installed($id);
        $manifest = $this->manifest($id);
        $this->assertNoProblems($id);

        if ($addon->version !== $manifest->version()) {
            throw new AddonException("Addon [{$id}] on disk is {$manifest->version()} but {$addon->version} is installed; run addon:update");
        }

        $addon->forceFill([
            'enabled'    => true,
            'enabled_at' => now(),
            'lock_hash'  => hash_file('sha256', $manifest->path.'/'.AddonIntegrity::LOCK),
        ])->save();

        Audit::record('addon.enabled', 'addon', $id, ['version' => $addon->version]);

        $this->load($manifest);
    }

    public function disable(string $id): void
    {
        $addon = $this->installed($id);

        $dependents = array_keys(array_filter(
            $this->enabledManifests(),
            fn (AddonManifest $m) => array_key_exists($id, $m->addonDependencies())
        ));
        if ($dependents !== []) {
            throw new AddonException("Cannot disable [{$id}]: required by ".implode(', ', $dependents));
        }

        $addon->forceFill(['enabled' => false])->save();
        unset($this->loaded[$id]); // code stays in memory for this process, but contributes nothing
        Audit::record('addon.disabled', 'addon', $id);
    }

    /**
     * Upgrade an installed addon to the version now on disk (migrations run forward).
     * Rollback = deploy the previous release and run addon:update again (down-migrations are
     * the addon author's responsibility and must be declared in its docs).
     */
    public function update(string $id): void
    {
        $addon = $this->installed($id);
        $manifest = $this->manifest($id);
        $this->assertNoProblems($id);

        if ($path = $manifest->migrationsPath()) {
            if (is_dir($path)) {
                Artisan::call('migrate', ['--path' => $path, '--realpath' => true, '--force' => true]);
            }
        }
        foreach ($manifest->permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $from = $addon->version;
        $addon->forceFill([
            'version'   => $manifest->version(),
            'path'      => $manifest->path,
            'lock_hash' => hash_file('sha256', $manifest->path.'/'.AddonIntegrity::LOCK),
        ])->save();

        Audit::record('addon.updated', 'addon', $id, ['from' => $from, 'to' => $manifest->version()]);
    }

    /**
     * Removes the registration. Data and tables are kept unless $purge (rolls back the
     * addon's migrations) - existing data is never deleted silently.
     */
    public function uninstall(string $id, bool $purge = false): void
    {
        $addon = $this->installed($id);
        if ($addon->enabled) {
            throw new AddonException("Disable [{$id}] before uninstalling it");
        }

        if ($purge && ($path = $this->manifest($id)->migrationsPath()) && is_dir($path)) {
            Artisan::call('migrate:rollback', ['--path' => $path, '--realpath' => true, '--force' => true]);
        }

        $addon->delete();
        Audit::record('addon.uninstalled', 'addon', $id, ['purged' => $purge]);
    }

    /**
     * Validate and store configuration. Secrets left blank keep their current value.
     *
     * @return string[] validation errors; [] = saved
     */
    public function configure(string $id, array $values): array
    {
        $this->installed($id);
        Tenancy::requireId(); // configuration (credentials) is per tenant
        $record = TenantAddon::firstOrCreate(['addon_id' => $id], ['enabled' => false]);
        $schema = $this->manifest($id)->configSchema();
        $current = (array) ($record->config ?? []);
        $errors = [];
        $new = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            $value = $values[$key] ?? null;
            $secret = (bool) ($field['secret'] ?? false);

            if ($secret && ($value === null || $value === '')) {
                $value = $current[$key] ?? null;
            }

            if (($value === null || $value === '') && ($field['required'] ?? false)) {
                $errors[] = "{$field['label']} is required";
                continue;
            }

            if ($value !== null && $value !== '') {
                $ok = match ($field['type']) {
                    'url'     => filter_var($value, FILTER_VALIDATE_URL) !== false && str_starts_with((string) $value, 'https://'),
                    'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
                    'select'  => in_array($value, (array) ($field['options'] ?? []), true),
                    default   => is_scalar($value) && strlen((string) $value) <= 4096,
                };
                if (! $ok) {
                    $errors[] = "{$field['label']} is not a valid {$field['type']}";
                    continue;
                }
            }

            $new[$key] = $value;
        }

        if ($errors !== []) {
            return $errors;
        }

        $record->forceFill(['config' => $new])->save();

        // Never log values; only which keys changed.
        $changed = array_keys(array_filter($new, fn ($v, $k) => ($current[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
        Audit::record('addon.configured', 'addon', $id, ['changed_keys' => $changed]);

        return [];
    }

    public function config(string $id, string $key, mixed $default = null): mixed
    {
        $value = $this->tenantRecord($id)?->config[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /** Config with secrets masked, for display. */
    public function maskedConfig(string $id): array
    {
        $this->installed($id);
        $config = (array) ($this->tenantRecord($id)?->config ?? []);
        $out = [];
        foreach ($this->manifest($id)->configSchema() as $field) {
            $value = $config[$field['key']] ?? null;
            $out[$field['key']] = ($field['secret'] ?? false) && $value !== null && $value !== '' ? '********' : $value;
        }

        return $out;
    }

    /** @return array<string, AddonManifest> enabled addons whose manifest is still present */
    public function enabledManifests(): array
    {
        try {
            if (! Schema::hasTable('addons')) {
                return [];
            }
            $ids = InstalledAddon::where('enabled', true)->pluck('id')->all();
        } catch (\Throwable) {
            return []; // no database yet (fresh install, artisan without DB)
        }

        $discovered = $this->discover();

        return array_intersect_key($discovered, array_flip($ids));
    }

    public function isEnabled(string $id): bool
    {
        return isset($this->enabledManifests()[$id]);
    }

    public function installed(string $id): InstalledAddon
    {
        return $this->installedOrNull($id) ?? throw new AddonException("Addon [{$id}] is not installed");
    }

    public function installedOrNull(string $id): ?InstalledAddon
    {
        try {
            return InstalledAddon::find($id);
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertNoProblems(string $id): void
    {
        $problems = $this->check($id);
        if ($problems !== []) {
            Audit::record('addon.rejected', 'addon', $id, ['problems' => $problems]);
            throw new AddonException("Addon [{$id}] failed verification: ".implode('; ', $problems));
        }
    }
}
