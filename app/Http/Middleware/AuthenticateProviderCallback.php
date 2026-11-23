<?php

namespace App\Http\Middleware;

use App\Core\Security\CallbackLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed authentication for inbound game-provider callbacks.
 * See config/game_providers.php. Usage: ->middleware('provider.callback:fivers')
 */
class AuthenticateProviderCallback
{
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $config = config("game_providers.callbacks.{$provider}");

        if (! is_array($config)) {
            return $this->reject($request, $provider, 'unknown provider', 503);
        }

        $ips    = (array) ($config['allowed_ips'] ?? []);
        $secret = (string) ($config['shared_secret'] ?? '');
        $source = (string) ($config['secret_source'] ?? '');

        if ($ips === [] && $secret === '') {
            return $this->reject($request, $provider, 'callback authentication not configured', 503);
        }

        if ($ips !== [] && ! IpUtils::checkIp((string) $request->ip(), $ips)) {
            return $this->reject($request, $provider, 'source ip not allowed', 403);
        }

        if ($secret !== '') {
            $presented = $this->presentedSecret($request, $source);
            if ($presented === null || ! hash_equals($secret, $presented)) {
                return $this->reject($request, $provider, 'invalid shared secret', 403);
            }
        }

        return $next($request);
    }

    private function presentedSecret(Request $request, string $source): ?string
    {
        [$kind, $name] = array_pad(explode(':', $source, 2), 2, '');

        $value = match ($kind) {
            'header' => $request->header($name),
            'body'   => $request->input($name),
            default  => null,
        };

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function reject(Request $request, string $provider, string $reason, int $status): Response
    {
        CallbackLog::record('provider', $provider, $request, 'rejected', $reason);

        return response()->json(['status' => 0, 'msg' => 'UNAUTHORIZED'], $status);
    }
}
