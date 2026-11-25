<?php

return [
    'mfa' => [
        // Staff roles that must use TOTP two-factor authentication in the admin/platform panels.
        'enforce'        => (bool) env('MFA_ENFORCE', true),
        'required_roles' => ['admin', 'super-admin'],
    ],

    // Content-Security-Policy. "baseline" is always enforced (no plugins, no <base> hijack, no
    // framing by other sites). "strict" is sent as Report-Only until tenant custom JS and
    // provider game iframes are inventoried; set CSP_ENFORCE_STRICT=true to enforce it.
    'csp' => [
        'baseline' => "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self' https://checkout.stripe.com",
        'strict' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://www.gstatic.com; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
            ."font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; "
            ."connect-src 'self' https: wss:; frame-src 'self' https:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'",
        'enforce_strict' => (bool) env('CSP_ENFORCE_STRICT', false),
        'report_uri' => env('CSP_REPORT_URI'),
    ],
];
