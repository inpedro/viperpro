<?php

namespace App\Core\Security;

use Illuminate\Support\Facades\Log;

/**
 * Guards outbound requests that carry gateway credentials.
 */
class TrustedEndpoint
{
    public static function isAllowed(string $gateway, ?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parts = parse_url($url);
        $host  = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            Log::warning('gateway.endpoint.rejected', ['gateway' => $gateway, 'reason' => 'not https']);
            return false;
        }

        $allowed = array_map('strtolower', (array) config("payments.allowed_hosts.{$gateway}", []));

        if (! in_array($host, $allowed, true)) {
            Log::warning('gateway.endpoint.rejected', ['gateway' => $gateway, 'host' => $host, 'reason' => 'host not in allowlist']);
            return false;
        }

        return true;
    }
}
