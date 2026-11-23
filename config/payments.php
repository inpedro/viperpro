<?php

/*
|--------------------------------------------------------------------------
| Payment gateway hardening
|--------------------------------------------------------------------------
| Outbound gateway API base URLs are stored in the database and editable in the
| admin panel. Credentials are only ever sent to hosts on these allowlists, so a
| tampered setting (or a malicious default, like the anonymous AWS Lambda URL that
| shipped as the "DigitoPay" endpoint) cannot exfiltrate merchant secrets.
|
| Hosts must be confirmed against each provider's official documentation /
| onboarding material. An empty list means the gateway is DISABLED (fail closed).
*/

$hosts = static fn (string $env, string $default = '') => array_values(array_filter(array_map(
    'trim', explode(',', (string) env($env, $default))
)));

return [
    // Core-registered gateway adapters. Gateways normally come from addons
    // (addons/payment-*, 'contributes.payment_gateways'); this list is for local overrides only.
    'gateways' => [],

    'allowed_hosts' => [
        // Host taken from the existing SuitPay configuration in this codebase; confirm with SuitPay.
        'suitpay'     => $hosts('SUITPAY_ALLOWED_HOSTS', 'ws.suitpay.app'),
        // No default on purpose: the shipped default was an unidentified AWS Lambda. Set from DigitoPay's docs.
        'digitopay'   => $hosts('DIGITOPAY_ALLOWED_HOSTS'),
        'mercadopago' => $hosts('MERCADOPAGO_ALLOWED_HOSTS', 'api.mercadopago.com'),
        'sharkpay'    => $hosts('SHARKPAY_ALLOWED_HOSTS', 'sharkpay.com.br'),
        // BsPay routes are not mounted; integration is disabled pending verification.
        'bspay'       => $hosts('BSPAY_ALLOWED_HOSTS'),
    ],
];
