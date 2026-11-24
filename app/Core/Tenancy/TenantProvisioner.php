<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use App\Models\CustomLayout;
use App\Models\Setting;

/**
 * Creates the per-tenant singleton rows every casino needs (settings, layout). Runs when a
 * tenant is created; idempotent. Values are neutral defaults the tenant admin then edits.
 */
final class TenantProvisioner
{
    public static function provision(Tenant $tenant): void
    {
        Tenancy::runAs($tenant, function () use ($tenant) {
            if (! Setting::query()->exists()) {
                Setting::create([
                    'software_name'       => $tenant->name,
                    'currency_code'       => (string) config('tenancy.default_currency', 'EUR'),
                    'prefix'              => (string) config('tenancy.default_currency_symbol', '€'),
                    'min_deposit'         => 10,
                    'max_deposit'         => 10000,
                    'min_withdrawal'      => 10,
                    'max_withdrawal'      => 10000,
                    'revshare_percentage' => 0,
                    'cpa_value'           => 0,
                    'cpa_baseline'        => 0,
                    'initial_bonus'       => 0,
                    'disable_rollover'    => 1,
                    'default_locale'      => (string) config('locales.default', 'en'),
                    'enabled_locales'     => json_encode([(string) config('locales.default', 'en')]),
                    'timezone'            => 'UTC',
                    'phone_field'         => 'required',
                    'document_field'      => 'hidden',
                ]);
            }

            if (! CustomLayout::query()->exists()) {
                CustomLayout::create(['custom_header' => '', 'custom_body' => '']);
            }
        });
    }
}
