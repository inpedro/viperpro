<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the request host (never from headers or input).
 * Runs as global middleware, before sessions/auth, for web, API and webhook routes alike.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenancy::reset(); // long-running workers: never inherit the previous request's tenant

        $host = TenantDomain::normalize($request->getHost());

        if (in_array($host, array_map([TenantDomain::class, 'normalize'], (array) config('tenancy.platform_domains', [])), true)) {
            Tenancy::setPlatform();
            return $next($request);
        }

        $tenant = TenantDomain::where('domain', $host)->first()?->tenant;

        if (! $tenant && config('tenancy.fallback_to_default')) {
            $tenant = Tenant::find(config('tenancy.default_tenant_id'));
        }

        if (! $tenant) {
            abort(404);
        }

        if (! $tenant->isActive()) {
            abort(503, 'This casino is temporarily unavailable.');
        }

        Tenancy::set($tenant);
        \App\Core\Tenancy\TenantRuntimeConfig::apply();

        return $next($request);
    }
}
