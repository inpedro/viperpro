<?php

namespace App\Core\Theming;

use App\Core\Tenancy\Tenancy;
use App\Models\CustomLayout;

/**
 * The current tenant's theme: a preset from config/themes.php plus validated overrides.
 * null theme = legacy mode (the tenant's existing custom_layouts colours are used as-is).
 */
final class Theme
{
    public const OVERRIDABLE_COLORS = ['primary', 'accent'];
    public const RADIUS_MIN = 0;
    public const RADIUS_MAX = 28;

    public static function presets(): array
    {
        return (array) config('themes.presets', []);
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::presets());
    }

    /** Active theme key for this tenant, or null for legacy colours. */
    public static function activeKey(): ?string
    {
        if (! Tenancy::id()) {
            return null;
        }

        try {
            $key = CustomLayout::query()->value('theme');
        } catch (\Throwable) {
            return null;
        }

        return self::exists($key) ? $key : null;
    }

    /** Resolved tokens (preset + sanitized overrides), or null in legacy mode. */
    public static function tokens(?string $key = null, ?array $overrides = null): ?array
    {
        $key ??= self::activeKey();
        if (! self::exists($key)) {
            return null;
        }

        if ($overrides === null) {
            try {
                $raw = CustomLayout::query()->value('theme_overrides');
                $overrides = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;
            } catch (\Throwable) {
                $overrides = [];
            }
        }

        $t = self::presets()[$key];
        $t['key'] = $key;

        foreach (self::sanitizeOverrides($overrides) as $k => $v) {
            if (in_array($k, self::OVERRIDABLE_COLORS, true)) {
                $t['colors'][$k] = $v;
                if ($k === 'primary') {
                    $t['colors']['primary-contrast'] = self::contrastFor($v);
                }
            } else {
                $t[$k] = $v;
            }
        }

        return $t;
    }

    /** Keep only well-formed overrides; anything else is dropped (no CSS injection path). */
    public static function sanitizeOverrides(array $overrides): array
    {
        $clean = [];
        foreach (self::OVERRIDABLE_COLORS as $c) {
            if (isset($overrides[$c]) && is_string($overrides[$c]) && preg_match('/^#[0-9A-Fa-f]{6}$/', $overrides[$c])) {
                $clean[$c] = strtoupper($overrides[$c]);
            }
        }
        if (isset($overrides['radius']) && filter_var($overrides['radius'], FILTER_VALIDATE_INT, ['options' => ['min_range' => self::RADIUS_MIN, 'max_range' => self::RADIUS_MAX]]) !== false) {
            $clean['radius'] = (int) $overrides['radius'];
        }
        foreach (['font', 'heading_font'] as $f) {
            if (isset($overrides[$f]) && is_string($overrides[$f]) && array_key_exists($overrides[$f], (array) config('themes.fonts'))) {
                $clean[$f] = $overrides[$f];
            }
        }

        return $clean;
    }

    /** CSS custom properties for :root (both hex and "r g b" triplets for alpha support). */
    public static function cssVariables(array $t): string
    {
        $fonts = (array) config('themes.fonts');
        $vars = [];

        foreach ($t['colors'] as $name => $hex) {
            $vars["--vp-{$name}"] = $hex;
            $vars["--vp-{$name}-rgb"] = implode(' ', self::rgb($hex));
        }
        $vars['--vp-radius'] = $t['radius'].'px';
        $vars['--vp-radius-sm'] = max(0, (int) round($t['radius'] / 2)).'px';
        $vars['--vp-font'] = $fonts[$t['font']]['family'] ?? $fonts['system']['family'];
        $vars['--vp-font-heading'] = $fonts[$t['heading_font']]['family'] ?? $vars['--vp-font'];

        // Legacy variables used across existing pages, so they adopt the theme too.
        $c = $t['colors'];
        $vars += [
            '--ci-primary-color' => $c['primary'],
            '--ci-primary-opacity-color' => 'rgb('.implode(' ', self::rgb($c['primary'])).' / 0.16)',
            '--ci-secundary-color' => $c['accent'],
            '--ci-gray-dark' => $c['surface'],
            '--ci-gray-light' => $c['border'],
            '--ci-gray-medium' => $c['muted'],
            '--ci-gray-over' => $c['surface-2'],
            '--title-color' => $c['text'],
            '--text-color' => $c['text'],
            '--sub-text-color' => $c['muted'],
            '--placeholder-color' => $c['muted'],
            '--background-color' => $c['bg'],
            '--standard-color' => $c['surface'],
            '--border-radius' => $t['radius'].'px',
        ];

        return implode('', array_map(fn ($k, $v) => "{$k}:{$v};", array_keys($vars), $vars));
    }

    public static function googleFontsUrl(array $t): ?string
    {
        $fonts = (array) config('themes.fonts');
        $families = array_values(array_unique(array_filter([
            $fonts[$t['font']]['google'] ?? null,
            $fonts[$t['heading_font']]['google'] ?? null,
        ])));

        return $families === [] ? null : 'https://fonts.googleapis.com/css2?family='.implode('&family=', $families).'&display=swap';
    }

    /** @return int[] */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Black or white text, whichever contrasts better (WCAG relative luminance). */
    public static function contrastFor(string $hex): string
    {
        [$r, $g, $b] = array_map(function (int $v) {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        $l = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.05) ? '#FFFFFF' : '#0B0D12';
    }
}
