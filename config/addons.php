<?php

/*
|--------------------------------------------------------------------------
| Addons (see docs/ADDONS.md)
|--------------------------------------------------------------------------
| Addons are installed from these directories only. There is no upload or
| runtime download path: code reaches the server through the deployment
| pipeline (git / composer), never through the admin panel.
*/

$keys = [];
foreach (array_filter(array_map('trim', explode(',', (string) env('ADDONS_TRUSTED_KEYS', '')))) as $pair) {
    [$id, $key] = array_pad(explode(':', $pair, 2), 2, '');
    if ($id !== '' && $key !== '') {
        $keys[$id] = $key;
    }
}

return [
    'platform_version' => '1.0.0',

    // trust => 'bundled': shipped inside this repository/release (same review boundary as core).
    // trust => 'third_party': anything else; always requires a trusted signature.
    'paths' => [
        ['path' => base_path('addons'), 'trust' => 'bundled'],
        ['path' => storage_path('app/addons-vendor'), 'trust' => 'third_party'],
    ],

    // Require addon.sig for bundled addons too (recommended once your pipeline signs releases).
    'require_signature_bundled' => (bool) env('ADDONS_REQUIRE_SIGNATURE_BUNDLED', false),

    // key id => base64 Ed25519 public key. Format: ADDONS_TRUSTED_KEYS="release-2026:BASE64,..."
    'trusted_keys' => $keys,
];
