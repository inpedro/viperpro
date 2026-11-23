<?php

namespace App\Core\Payments;

use App\Core\Addons\AddonManager;
use App\Models\Gateway;

/**
 * Gateway credential lookup: the addon's encrypted configuration first, then the legacy
 * plaintext `gateways` table column (kept so existing installs keep working until the
 * operator re-enters credentials in the addon manager; see docs/ADDONS.md).
 */
final class GatewayCredentials
{
    public static function get(string $addonId, string $key, ?string $legacyColumn = null): string
    {
        $value = app(AddonManager::class)->config($addonId, $key);
        if (is_scalar($value) && (string) $value !== '') {
            return (string) $value;
        }

        if ($legacyColumn === null) {
            return '';
        }

        return (string) (Gateway::first()?->getAttributes()[$legacyColumn] ?? '');
    }
}
