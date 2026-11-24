<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use JWTAuth;

class JWTMiddleWare
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('api')->check()) {
            // The user lookup is tenant-scoped already; additionally require that the user AND the
            // token's tenant claim match the tenant resolved from the domain (defence in depth).
            $user = auth('api')->user();
            $sameTenant = $user->tenant_id !== null && (int) $user->tenant_id === (int) \App\Core\Tenancy\Tenancy::id();

            try {
                $tid = auth('api')->payload()->get('tid');
                $sameTenant = $sameTenant && $tid !== null && (int) $tid === (int) $user->tenant_id;
            } catch (\Tymon\JWTAuth\Exceptions\JWTException) {
                // No token in the request (e.g. an authenticated test client): user check only.
            }

            // Tokens issued before a password reset / "log out everywhere" are revoked.
            try {
                $iat = (int) auth('api')->payload()->get('iat');
                if ($user->tokens_valid_after !== null && $iat < $user->tokens_valid_after->getTimestamp()) {
                    $sameTenant = false;
                }
            } catch (\Tymon\JWTAuth\Exceptions\JWTException) {
            }

            if ($sameTenant) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'unauthenticated'], 401);
    }
}
