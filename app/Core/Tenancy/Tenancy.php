<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use Closure;
use LogicException;

/**
 * The current tenant for this request / job / command.
 *
 * States:
 *   tenant    - a casino brand: tenant-owned models are scoped to its id
 *   platform  - the super-admin console: scoped models see only platform rows (tenant_id NULL)
 *   unscoped  - explicit cross-tenant maintenance (Tenancy::acrossTenants), never for requests
 */
final class Tenancy
{
    private static ?Tenant $tenant = null;
    private static bool $platform = false;
    private static int $unscoped = 0;

    public static function set(?Tenant $tenant): void
    {
        self::$tenant = $tenant;
        self::$platform = false;
    }

    public static function setPlatform(): void
    {
        self::$tenant = null;
        self::$platform = true;
    }

    public static function reset(): void
    {
        self::$tenant = null;
        self::$platform = false;
        self::$unscoped = 0;
    }

    public static function current(): ?Tenant
    {
        return self::$tenant;
    }

    public static function id(): ?int
    {
        return self::$tenant?->id;
    }

    public static function isPlatform(): bool
    {
        return self::$platform;
    }

    public static function isUnscoped(): bool
    {
        return self::$unscoped > 0;
    }

    /** Resolved = a tenant or the platform context. */
    public static function isResolved(): bool
    {
        return self::$tenant !== null || self::$platform;
    }

    public static function requireId(): int
    {
        return self::id() ?? throw new LogicException('No tenant in the current context');
    }

    /** Run $callback as $tenant, restoring the previous context afterwards. */
    public static function runAs(Tenant $tenant, Closure $callback): mixed
    {
        [$prevTenant, $prevPlatform] = [self::$tenant, self::$platform];
        self::set($tenant);

        try {
            return $callback($tenant);
        } finally {
            [self::$tenant, self::$platform] = [$prevTenant, $prevPlatform];
        }
    }

    /** Explicit cross-tenant section (reconciliation, platform reporting). Use sparingly. */
    public static function acrossTenants(Closure $callback): mixed
    {
        self::$unscoped++;
        try {
            return $callback();
        } finally {
            self::$unscoped--;
        }
    }

    /** Cache key namespaced by tenant (cache stores are shared between tenants). */
    public static function key(string $key): string
    {
        return (self::$platform ? 'platform' : 't'.(self::id() ?? 0)).':'.$key;
    }

    /** Storage path prefix for tenant files. */
    public static function storagePrefix(): string
    {
        return self::$platform ? 'platform' : 'tenants/'.self::requireId();
    }
}
