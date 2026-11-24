<?php

namespace App\Http\Middleware;

use App\Core\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the request locale (see Locales::resolve). Runs after ResolveTenant, so the
 * tenant's enabled locales and default are known. Never applies an arbitrary string.
 */
class SetDefaultLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $userLanguage = null;
        try {
            if (auth('api')->check()) {
                $userLanguage = auth('api')->user()->language;
            }
        } catch (\Throwable) {
            // malformed token: treat as guest; JWT middleware handles auth errors
        }

        $locale = Locales::resolve($request, $userLanguage);
        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', Locales::htmlLang($locale));

        return $response;
    }
}
