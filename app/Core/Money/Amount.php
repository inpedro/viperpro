<?php

namespace App\Core\Money;

/**
 * Minimal exact decimal handling for 2-decimal currency amounts using integer minor units.
 * No floats, no bcmath dependency. Phase 2 replaces this with a full Money value object
 * that carries currency and per-currency precision (e.g. 0 for JPY, 6+ for USDT).
 */
final class Amount
{
    /**
     * Parse a user/gateway supplied amount ("10", "10.5", "10.50") into minor units.
     * Returns null for anything that is not a plain non-exponent decimal with <= 2 decimals.
     */
    public static function toMinor(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (! preg_match('/^(-?)(\d{1,15})(?:\.(\d{1,2}))?$/', $value, $m)) {
            return null;
        }

        $minor = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Normalise to a canonical "123.45" string, or null if invalid.
     */
    public static function normalize(mixed $value): ?string
    {
        $minor = self::toMinor($value);

        return $minor === null ? null : self::fromMinor($minor);
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        $x = self::toMinor($a);
        $y = self::toMinor($b);

        return $x !== null && $x === $y;
    }
}
