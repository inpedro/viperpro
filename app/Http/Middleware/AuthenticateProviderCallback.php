<?php

namespace App\Http\Middleware;

use App\Core\Security\CallbackLog;
use App\Core\Security\ProviderCallbackAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed authentication for inbound game-provider callbacks.
 * See config/game_providers.php. Usage: ->middleware('provider.callback:fivers')
 */
class AuthenticateProviderCallback
{
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $reason = ProviderCallbackAuth::check($provider, $request);

        if ($reason !== null) {
            CallbackLog::record('provider', $provider, $request, 'rejected', $reason);
            $status = in_array($reason, [ProviderCallbackAuth::NOT_CONFIGURED, 'unknown provider'], true) ? 503 : 403;

            return response()->json(['status' => 0, 'msg' => 'UNAUTHORIZED'], $status);
        }

        return $next($request);
    }
}
