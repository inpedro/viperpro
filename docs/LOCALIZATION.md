# Localization and Internationalization

_Phase 5. Code: `app/Core/Localization`, `config/locales.php`, `lang/`._

## Defaults
* Platform default language: **English** (`config('locales.default')`, `app.locale`).
* Storage timezone: `APP_TIMEZONE`, default **UTC**.
  > **Existing installs must set `APP_TIMEZONE=America/Sao_Paulo`.** The old code stored naive timestamps in that zone; changing the storage zone would shift every existing date.
* New tenants are provisioned with English, EUR, UTC display time, a required phone field and no national-ID field.
* Existing tenant #1 keeps `pt_BR` as its default, with `pt_BR`/`en`/`es` enabled, display timezone `America/Sao_Paulo` and an optional CPF field. That is the old behaviour, now as data.

## Installed locales
| Code | Language | Strings | Laravel files | Notes |
|---|---|---|---|---|
| `en` | English | source | yes | reference language |
| `sq` | Albanian (Shqip) | 100% | yes | written for this release. Laravel validation text from Laravel-Lang (MIT). **Needs native-speaker review before launch.** |
| `es` | Spanish | 100% | yes | gaps filled in this release |
| `pt_BR` | Portuguese (Brazil) | 100% | yes | keeps the original Portuguese wording of every former hardcoded message |

A locale is installed when it is listed in `config/locales.php` **and** `lang/{code}.json` exists. Each entry declares a native name, an ICU locale for formatting, and the text direction (`ltr`/`rtl`).

### Adding a locale
1. Add `lang/{code}.json` (copy `en.json` and translate the values) and `lang/{code}/{auth,pagination,passwords,validation}.php`.
2. Add `'{code}' => ['name' => ..., 'native' => ..., 'intl' => ..., 'dir' => 'ltr|rtl']` to `config/locales.php`.
3. Run `php artisan lang:check --strict`; CI should run it too, since it is a test.
4. Tenants enable it in **Admin → Modulos → Languages & registration**.

Filament ships its own translations for 50+ languages (including `sq` and `ar`), so the admin chrome follows automatically.

## Per-tenant policy (`settings`)
| Column | Meaning |
|---|---|
| `enabled_locales` | locales the casino offers (JSON list) |
| `default_locale` | fallback when neither the player nor the browser picks one |
| `timezone` | display timezone for dates (`Formatter::dateTime`) |
| `country_code` | main market (informational) |
| `phone_field` / `document_field` | `required` / `optional` / `hidden` |
| `document_type` | `cpf` (checksum validated) or a free-text ID such as `national_id` or `passport` |

## Locale resolution (`SetDefaultLanguage` → `Locales::resolve`)
1. The authenticated player's saved language, if this casino offers it.
2. The best `Accept-Language` match: exact (`pt-BR`), then language-only (`pt` → `pt_BR`).
3. The tenant default.

Arbitrary values are never applied: `PUT /api/profile/updateLanguage` returns 422 for locales the casino doesn't offer. The response carries `Content-Language`, and the HTML root gets `lang` and `dir` (RTL ready). The language selector lists the enabled locales by their native names, with no flags, because a flag is a country, not a language.

APIs:
* `GET /api/settings/locales`: current, default, timezone, and enabled locales with native name and direction.
* `GET /api/settings/registration`: which registration fields to show.

## Formatting
`App\Core\Localization\Formatter` (ICU through ext-intl):
* `money(Money, locale)`: `€1,234.50` (en), `1 234,50 €` (sq), `R$ 1.234,50` (pt_BR).
* `number()`, `date()`, `dateTime()` in the tenant's display timezone.

Stored values stay locale-neutral: integer minor units and the storage timezone.

## Removing the Brazil assumptions
| Before | Now |
|---|---|
| `app.locale = pt_BR`, `timezone = America/Sao_Paulo` | `en`, UTC; per-tenant locale and display timezone |
| Phone stored as stripped Brazilian digits | international (E.164, `+` kept); legacy local digits still accepted |
| CPF field implied by the frontend | per-tenant `document_field`, with CPF validation only when `document_type = cpf` |
| `users.language` default `pt_BR` | nullable (no preference) |
| Hardcoded Portuguese API messages | English keys, translated in every locale |
| Flag-based selector, including the Brazilian flag | native-name list from the tenant's enabled locales |
| `en.json` "Software Name" = another brand, "Earn R$100 free" | neutral wording |
| Password-reset e-mail in Portuguese | translated |

Brazil-specific integrations (the PIX gateways) are optional addons, as of Phase 3.

## Not done yet (tracked)
* **Admin panels (admin, affiliate, platform):**
  * About 470 strings are translated in all four languages: labels, sections, navigation, notifications and options.
  * The panels follow the staff member's browser language among the casino's enabled languages (`AdminPanelSmokeTest::test_the_admin_panel_is_localized`).
  * `lang:check --strict` now scans `app/Filament` and `app/Livewire` too.
  * Brand names and technical identifiers (API keys, Client ID, RTP, …) are deliberately left as they are.
  * Amounts in admin tables use the casino's currency instead of a hardcoded BRL/`R$`.
* **Vue literals:** 10 components still contain Portuguese text outside `$t()`. Handled in the frontend redesign (Phase 6), which will also consume `/api/settings/registration` and the locale-aware formatters.
* **Withdrawal method types** (`pix` / `bank`) and PIX key validation are still part of the core withdrawal API. They move behind gateway capabilities with the Phase 7 adapters.
* **Albanian review** by a native speaker is required before production use.
