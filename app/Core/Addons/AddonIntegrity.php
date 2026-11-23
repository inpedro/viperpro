<?php

namespace App\Core\Addons;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Package integrity and authenticity.
 *
 *   addon.lock  {"id","version","files":{relative path: sha256}} - every file in the addon
 *   addon.sig   {"key_id","signature"} - Ed25519 detached signature over the exact addon.lock bytes,
 *               by a key listed in config('addons.trusted_keys')
 *
 * Verification fails closed: a missing/extra/modified file, an unknown key or a bad signature
 * means the addon cannot be installed or enabled.
 */
final class AddonIntegrity
{
    public const LOCK = 'addon.lock';
    public const SIG = 'addon.sig';

    /** @return array<string, string> */
    public static function hashFiles(string $path): array
    {
        $path = rtrim($path, '/');
        $hashes = [];

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isLink()) {
                throw new AddonException("Symlinks are not allowed in addons ({$file->getPathname()})");
            }
            if (! $file->isFile()) {
                continue;
            }
            $relative = ltrim(substr($file->getPathname(), strlen($path)), '/');
            if (in_array($relative, [self::LOCK, self::SIG], true)) {
                continue;
            }
            $hashes[$relative] = hash_file('sha256', $file->getPathname());
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    public static function buildLock(AddonManifest $manifest): string
    {
        return json_encode([
            'id'      => $manifest->id(),
            'version' => $manifest->version(),
            'files'   => self::hashFiles($manifest->path),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    public static function writeLock(AddonManifest $manifest): void
    {
        file_put_contents($manifest->path.'/'.self::LOCK, self::buildLock($manifest));
    }

    /**
     * @return string[] problems; [] = verified
     */
    public static function verify(AddonManifest $manifest, bool $requireSignature): array
    {
        $lockFile = $manifest->path.'/'.self::LOCK;
        if (! is_file($lockFile)) {
            return ['addon.lock is missing (run php artisan addon:lock in the build pipeline)'];
        }

        $lockBytes = (string) file_get_contents($lockFile);
        $lock = json_decode($lockBytes, true);
        if (! is_array($lock) || ($lock['id'] ?? null) !== $manifest->id() || ($lock['version'] ?? null) !== $manifest->version()) {
            return ['addon.lock does not match the manifest id/version'];
        }

        $problems = [];
        $expected = (array) ($lock['files'] ?? []);
        $actual = self::hashFiles($manifest->path);

        foreach ($expected as $file => $hash) {
            if (! isset($actual[$file])) {
                $problems[] = "missing file {$file}";
            } elseif (! hash_equals((string) $hash, $actual[$file])) {
                $problems[] = "modified file {$file}";
            }
        }
        foreach (array_diff_key($actual, $expected) as $file => $_) {
            $problems[] = "unexpected file {$file}";
        }

        $sigFile = $manifest->path.'/'.self::SIG;
        if (is_file($sigFile)) {
            $problems = array_merge($problems, self::verifySignature($lockBytes, (string) file_get_contents($sigFile)));
        } elseif ($requireSignature) {
            $problems[] = 'addon.sig is missing and signatures are required';
        }

        return $problems;
    }

    /** @return string[] */
    private static function verifySignature(string $lockBytes, string $sigJson): array
    {
        $sig = json_decode($sigJson, true);
        $keyId = is_array($sig) ? (string) ($sig['key_id'] ?? '') : '';
        $keys = (array) config('addons.trusted_keys', []);

        if ($keyId === '' || ! isset($keys[$keyId])) {
            return ["addon.sig is signed by an untrusted key [{$keyId}]"];
        }

        $public = base64_decode((string) $keys[$keyId], true);
        $signature = base64_decode((string) ($sig['signature'] ?? ''), true);

        if ($public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $lockBytes, $public)) {
            return ['addon.sig signature is invalid'];
        }

        return [];
    }

    /** @return array{public: string, secret: string} base64 */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        ];
    }

    public static function sign(AddonManifest $manifest, string $keyId, string $secretKeyBase64): void
    {
        $secret = base64_decode($secretKeyBase64, true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new AddonException('Invalid Ed25519 secret key');
        }

        $lockFile = $manifest->path.'/'.self::LOCK;
        if (! is_file($lockFile)) {
            throw new AddonException('Generate addon.lock before signing');
        }

        file_put_contents($manifest->path.'/'.self::SIG, json_encode([
            'key_id'    => $keyId,
            'signature' => base64_encode(sodium_crypto_sign_detached((string) file_get_contents($lockFile), $secret)),
        ], JSON_PRETTY_PRINT)."\n");
    }
}
