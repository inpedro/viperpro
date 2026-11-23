<?php

namespace App\Core\Security;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Fail-closed authentication of inbound game-provider callbacks (config/game_providers.php).
 * Returns null when the request is authenticated, otherwise the rejection reason.
 */
final class ProviderCallbackAuth
{
    public const NOT_CONFIGURED = 'callback authentication not configured';

    public static function check(string $provider, Request $request): ?string
    {
        $config = config("game_providers.callbacks.{$provider}");
        if (! is_array($config)) {
            return 'unknown provider';
        }

        $ips    = (array) ($config['allowed_ips'] ?? []);
        $secret = (string) ($config['shared_secret'] ?? '');
        $source = (string) ($config['secret_source'] ?? '');

        if ($ips === [] && $secret === '') {
            return self::NOT_CONFIGURED;
        }

        if ($ips !== [] && ! IpUtils::checkIp((string) $request->ip(), $ips)) {
            return 'source ip not allowed';
        }

        if ($secret !== '') {
            [$kind, $name] = array_pad(explode(':', $source, 2), 2, '');
            $presented = match ($kind) {
                'header' => $request->header($name),
                'body'   => $request->input($name),
                default  => null,
            };

            if (! is_string($presented) || $presented === '' || ! hash_equals($secret, $presented)) {
                return 'invalid shared secret';
            }
        }

        return null;
    }

    public static function isConfigured(string $provider): bool
    {
        $config = (array) config("game_providers.callbacks.{$provider}", []);

        return ! empty($config['allowed_ips']) || ! empty($config['shared_secret']);
    }
}
