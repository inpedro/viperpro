<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Baseline security headers on every response (config/security.php). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.stripe.com")');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $csp = (array) config('security.csp');
        $report = ! empty($csp['report_uri']) ? '; report-uri '.$csp['report_uri'] : '';
        if (! empty($csp['enforce_strict'])) {
            $h->set('Content-Security-Policy', $csp['strict'].'; '.$csp['baseline'].$report);
        } else {
            $h->set('Content-Security-Policy', $csp['baseline']);
            $h->set('Content-Security-Policy-Report-Only', $csp['strict'].$report);
        }

        return $response;
    }
}
