<?php

namespace App\Core\Addons;

/**
 * Minimal semantic-version matching for addon compatibility (no external dependency).
 * Supports: exact "1.2.3", "^1.2", "~1.2.3", ">=1.0.0", "<2.0.0", "*" and space-separated AND.
 */
final class Semver
{
    public static function isValid(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version);
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        if (! self::isValid($version)) {
            return false;
        }

        foreach (preg_split('/\s+/', trim($constraint)) as $part) {
            if (! self::satisfiesOne($version, $part)) {
                return false;
            }
        }

        return true;
    }

    private static function satisfiesOne(string $version, string $c): bool
    {
        if ($c === '*' || $c === '') {
            return true;
        }

        if (preg_match('/^(>=|<=|>|<|=)?(\d+(?:\.\d+){0,2})$/', $c, $m) && ($m[1] ?? '') !== '') {
            return version_compare($version, self::pad($m[2]), match ($m[1]) {
                '>=' => '>=', '<=' => '<=', '>' => '>', '<' => '<', default => '==',
            });
        }

        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $c, $m)) {
            $min = self::pad($c = substr($c, 1));
            $major = (int) $m[1];
            $max = $major > 0 ? ($major + 1).'.0.0' : '0.'.(((int) ($m[2] ?? 0)) + 1).'.0';
            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if (preg_match('/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $c, $m)) {
            $min = self::pad(substr($c, 1));
            $max = $m[1].'.'.(((int) $m[2]) + 1).'.0';
            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if (self::isValid($c)) {
            return version_compare($version, $c, '==');
        }

        return false; // unknown constraint syntax: refuse
    }

    private static function pad(string $v): string
    {
        $parts = explode('.', $v);
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', array_slice($parts, 0, 3));
    }
}
