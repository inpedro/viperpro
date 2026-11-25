<?php

namespace App\Core\Localization;

use App\Core\Money\Money;
use DateTimeInterface;
use IntlDateFormatter;
use NumberFormatter;

/**
 * Locale-aware formatting (ICU via ext-intl). Stored values stay locale-neutral
 * (minor units, UTC/app timezone); only presentation is localized.
 */
final class Formatter
{
    /** ISO code of the current casino's default currency (admin tables without a per-row currency). */
    public static function tenantCurrency(): string
    {
        return strtoupper((string) (\App\Helpers\Core::getSetting()?->currency_code ?: config('tenancy.default_currency', 'EUR')));
    }

    /** The current casino's currency symbol, for input affixes. */
    public static function tenantCurrencySymbol(): string
    {
        return (string) (\App\Helpers\Core::getSetting()?->prefix ?: self::tenantCurrency());
    }

    public static function money(Money $money, ?string $locale = null): string
    {
        $fmt = new NumberFormatter(self::intl($locale), NumberFormatter::CURRENCY);
        $out = $fmt->formatCurrency($money->minor / 100, $money->currency);

        return $out === false ? $money->toDecimal().' '.$money->currency : $out;
    }

    public static function number(int|float $value, int $decimals = 2, ?string $locale = null): string
    {
        $fmt = new NumberFormatter(self::intl($locale), NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return (string) $fmt->format($value);
    }

    /** Date/time in the tenant's display timezone. */
    public static function dateTime(DateTimeInterface $at, ?string $locale = null, int $date = IntlDateFormatter::MEDIUM, int $time = IntlDateFormatter::SHORT): string
    {
        $fmt = new IntlDateFormatter(self::intl($locale), $date, $time, Locales::timezone());

        return (string) $fmt->format($at);
    }

    public static function date(DateTimeInterface $at, ?string $locale = null): string
    {
        return self::dateTime($at, $locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
    }

    private static function intl(?string $locale): string
    {
        return Locales::meta($locale ?? app()->getLocale())['intl'];
    }
}
