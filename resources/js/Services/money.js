// Locale-aware money formatting for the SPA (mirrors App\Core\Localization\Formatter).
// Currency comes from the casino settings; locale from <html lang>, set by the language selector.
export function formatMoney(amount, currency, locale) {
    const value = Number(amount ?? 0);
    const lang = locale || document.documentElement.lang || 'en';
    try {
        return new Intl.NumberFormat(lang, { style: 'currency', currency: currency || 'EUR' }).format(value);
    } catch (e) {
        return `${value.toFixed(2)} ${currency || ''}`.trim();
    }
}

export function formatCompactMoney(amount, currency, locale) {
    const lang = locale || document.documentElement.lang || 'en';
    try {
        return new Intl.NumberFormat(lang, { style: 'currency', currency: currency || 'EUR', notation: 'compact', maximumFractionDigits: 0 }).format(Number(amount ?? 0));
    } catch (e) {
        return String(amount);
    }
}
