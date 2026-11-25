<?php

namespace App\Core\Security;

/**
 * HMAC-SHA256 signed, expiring tokens for game sessions and provider player references.
 *
 * Replaces Helper::MakeToken/DecToken, which were base64 plus a fixed letter-substitution
 * table with no secret: anyone could mint a token for any user id and spin on their wallet.
 *
 * Format: base64url(json payload) "." base64url(hmac)
 * The signing key is derived from APP_KEY with a purpose label, so these tokens cannot be
 * confused with other HMACs the application produces.
 */
final class SignedToken
{
    private const PURPOSE = 'viperpro.game-session.v2';

    /**
     * @param array    $claims Application claims (e.g. ['id' => userId, 'game' => code]).
     * @param int|null $ttl    Seconds until expiry. null = config default. 0 = no expiry, deterministic
     *                         (only for stable provider-side player identifiers, never for wallet access by itself).
     */
    public static function issue(array $claims, ?int $ttl = null): string
    {
        $ttl ??= (int) config('game_sessions.ttl', 14400);

        unset($claims['status'], $claims['exp'], $claims['iat'], $claims['jti']);

        if ($ttl > 0) {
            $claims['iat'] = now()->getTimestamp();
            $claims['exp'] = now()->getTimestamp() + $ttl;
            $claims['jti'] = bin2hex(random_bytes(8));
        }

        ksort($claims);
        $payload = self::b64(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $payload.'.'.self::b64(self::mac($payload));
    }

    /**
     * Returns the claims with 'status' => true, or ['status' => false, 'message' => ...].
     * The shape matches what callers of the old DecToken() expect.
     */
    public static function verify(string $token): array
    {
        $invalid = ['status' => false, 'message' => 'invalid token'];

        if (substr_count($token, '.') !== 1 || strlen($token) > 4096) {
            return $invalid;
        }

        [$payload, $signature] = explode('.', $token, 2);
        $expected = self::b64(self::mac($payload));

        if (! hash_equals($expected, $signature)) {
            return $invalid;
        }

        $claims = json_decode((string) self::unb64($payload), true);
        if (! is_array($claims)) {
            return $invalid;
        }

        if (isset($claims['exp']) && (int) $claims['exp'] < now()->getTimestamp()) {
            return ['status' => false, 'message' => 'token expired'];
        }

        return ['status' => true] + $claims;
    }

    private static function mac(string $payload): string
    {
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new \RuntimeException('APP_KEY must be set to sign game session tokens.');
        }

        $key = hash_hmac('sha256', self::PURPOSE, $appKey, true);

        return hash_hmac('sha256', $payload, $key, true);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
