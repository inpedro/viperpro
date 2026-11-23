<?php

/*
|--------------------------------------------------------------------------
| Multi-tenancy (see docs/TENANCY.md)
|--------------------------------------------------------------------------
| Shared database, tenant_id on every tenant-owned table, enforced by a global
| scope. The tenant is resolved from the request host via tenant_domains; it is
| NEVER taken from request input.
*/

$list = static fn (string $env, string $default = '') => array_values(array_filter(array_map('trim', explode(',', (string) env($env, $default)))));

return [
    // Unknown hosts fall back to the default tenant (single-brand installs). Set false
    // when running several brands so an unknown host gets 404 instead of brand #1.
    'fallback_to_default' => (bool) env('TENANCY_FALLBACK_DEFAULT', true),

    'default_tenant_id' => 1,

    // Hosts that serve the platform (super admin) console instead of a casino.
    'platform_domains' => $list('PLATFORM_DOMAINS'),
];
