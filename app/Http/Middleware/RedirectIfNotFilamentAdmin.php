<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards non-Filament routes that perform administrative actions.
 *
 * Previously this extended Authenticate and *returned* a redirect from authenticate(),
 * a return value Laravel discards, so any logged-in user passed. It also allowed the
 * 'afiliado' role and skipped the check entirely when APP_ENV=local.
 */
class RedirectIfNotFilamentAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['error' => 'unauthenticated'], 401)
                : redirect()->guest(Filament::getPanel('admin')->getLoginUrl());
        }

        if (! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403);
        }

        return $next($request);
    }
}
