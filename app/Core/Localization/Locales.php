<?php

namespace App\Core\Localization;

use App\Core\Tenancy\Tenancy;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Locale registry + per-tenant locale policy.
 *
 * Resolution order for a request: authenticated player's saved language -> best
 * Accept-Language match -> tenant default -> platform default. Only locales that are
 * installed AND enabled for the tenant are ever applied.
 */
final class Locales
{
    /** @return array<string, array{name: string, native: string, intl: string, dir: string}> */
    public static function installed(): array
    {
        return array_filter(
            (array) config('locales.installed', []),
            fn (string $code) => is_file(lang_path("{$code}.json")),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function isInstalled(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::installed());
    }

    public static function platformDefault(): string
    {
        return (string) config('locales.default', 'en');
    }

    private static function tenantSettings(): ?Setting
    {
        if (! Tenancy::id()) {
            return null;
        }

        try {
            return Setting::query()->first(['default_locale', 'enabled_locales', 'timezone']);
        } catch (\Throwable) {
            return null; // columns not migrated yet
        }
    }

    /** @return string[] */
    public static function enabled(): array
    {
        $raw = self::tenantSettings()?->getAttributes()['enabled_locales'] ?? null;
        $list = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $list = array_values(array_filter($list, [self::class, 'isInstalled']));

        return $list !== [] ? $list : [self::tenantDefault()];
    }

    public static function isEnabled(?string $code): bool
    {
        return $code !== null && in_array($code, self::enabled(), true);
    }

    public static function tenantDefault(): string
    {
        $code = self::tenantSettings()?->default_locale;

        return self::isInstalled($code) ? $code : self::platformDefault();
    }

    public static function timezone(): string
    {
        $tz = self::tenantSettings()?->timezone;

        return is_string($tz) && in_array($tz, timezone_identifiers_list(), true) ? $tz : (string) config('app.timezone', 'UTC');
    }

    public static function resolve(Request $request, ?string $userLanguage = null): string
    {
        if (self::isEnabled($userLanguage)) {
            return $userLanguage;
        }

        $enabled = self::enabled();
        foreach ($request->getLanguages() as $accepted) {
            // Exact (pt_BR), then language only (pt -> first enabled pt_*).
            if (in_array($accepted, $enabled, true)) {
                return $accepted;
            }
            $lang = strtolower(strtok($accepted, '_-'));
            foreach ($enabled as $code) {
                if (strtolower(strtok($code, '_')) === $lang) {
                    return $code;
                }
            }
        }

        $default = self::tenantDefault();

        return in_array($default, $enabled, true) ? $default : $enabled[0];
    }

    public static function meta(string $code): array
    {
        return self::installed()[$code] ?? ['name' => $code, 'native' => $code, 'intl' => $code, 'dir' => 'ltr'];
    }

    public static function direction(?string $code = null): string
    {
        return self::meta($code ?? app()->getLocale())['dir'] === 'rtl' ? 'rtl' : 'ltr';
    }

    /** BCP 47 tag for <html lang>: pt_BR -> pt-BR */
    public static function htmlLang(?string $code = null): string
    {
        return str_replace('_', '-', $code ?? app()->getLocale());
    }
}
