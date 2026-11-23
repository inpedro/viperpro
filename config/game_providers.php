<?php

/*
|--------------------------------------------------------------------------
| Game provider callback authentication (fail closed)
|--------------------------------------------------------------------------
| None of the existing provider callbacks verified who was calling them: any
| client could POST a "win" for any user id. Every provider callback route now
| passes through App\Http\Middleware\AuthenticateProviderCallback, which REJECTS
| the request unless at least one authentication method is configured for that
| provider and every configured method passes:
|
|  - allowed_ips:   comma-separated IPs / CIDR ranges published by the provider.
|  - shared_secret: a secret the provider sends on every callback, compared in
|                   constant time against `secret_source` ("header:<Name>" or
|                   "body:<field>").
|
| The correct values MUST come from each provider's integration documentation /
| onboarding. They are intentionally not guessed here. Until configured, the
| provider's callbacks return 503 and are written to callback_logs.
*/

$list = static fn (string $env) => array_values(array_filter(array_map('trim', explode(',', (string) env($env, '')))));

$provider = static fn (string $prefix, string $defaultSecretSource = '') => [
    'allowed_ips'   => $list("{$prefix}_CALLBACK_IPS"),
    'shared_secret' => (string) env("{$prefix}_CALLBACK_SECRET", ''),
    'secret_source' => (string) env("{$prefix}_CALLBACK_SECRET_SOURCE", $defaultSecretSource),
];

return [
    'callbacks' => [
        'fivers'      => $provider('FIVERS'),        // routes: gold_api, gold_api/*
        'venix'       => $provider('VENIX'),         // route: venix_api
        'playigaming' => $provider('PLAYIGAMING'),   // route: playigaming_api
        'evergame'    => $provider('EVERGAME'),      // route: ever
        'playgaming'  => $provider('PLAYGAMING'),    // route: playgaming
        'kagaming'    => $provider('KAGAMING'),      // route: kagaming/webhooks (not mounted)
        'vibra'       => $provider('VIBRA'),         // route: vibra/webhooks (not mounted)
        'salsa'       => $provider('SALSA'),         // route: cron/salsa/webhook
    ],
];
