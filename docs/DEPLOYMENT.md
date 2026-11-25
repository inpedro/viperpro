# Deployment and Upgrade Guide

_Phase 9. This covers staging. **Real-money operation also requires every item in `RELEASE_CHECKLIST.md`.**_

## Requirements
* PHP 8.2+ (tested on 8.3) with `intl`, `sodium`, `gd`, `zip`, `pdo_mysql`. The application runs on **Laravel 12**.
* MySQL 8 / MariaDB 10.6+, **InnoDB for every table**. Wallet and settlement locks rely on row locking.
* Node 22 for the frontend build.
* Redis is recommended for cache, queue and sessions.
* A process supervisor for `queue:work` and a cron entry for the scheduler.

## Fresh install
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then fill it in (see below)
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan addon:sync --enable        # installs bundled addons, activates them for casino #1
php artisan viper:create-admin admin@example.com                 # casino admin (tenant 1)
php artisan viper:create-admin owner@example.com --platform      # platform super-admin
npm ci && npm run build
php artisan optimize
```
Cron: `* * * * * php /path/artisan schedule:run`. The scheduler runs `wallet:reconcile`, `addon:package verify` and `integrations:status --all` hourly.

## Upgrading an existing installation (from the original Viper Pro)
1. **Back up the database.** Then do the mandatory actions in `SECURITY_AUDIT.md` §1: rotate credentials, revoke the leaked Google key, reset admin passwords.
2. Set `APP_TIMEZONE=America/Sao_Paulo` if the old install relied on the previously hardcoded timezone. Stored timestamps were written in that zone. Per-casino display time zones are now set in Admin → Localization.
3. `php artisan migrate --force`. Migrations are reversible except the baseline (`2014_10_12_000002`), which keeps tables that may predate it. The Phase 1 migration converts MyISAM tables to InnoDB; verify with `SHOW TABLE STATUS WHERE Engine <> 'InnoDB'`.
4. `php artisan addon:sync --enable`, then `php artisan addon:list`.
5. `php artisan wallet:reconcile --open` opens ledger balances for existing wallets. Afterwards `php artisan wallet:reconcile` must report no differences.
6. Admins must enrol two-factor authentication on their next visit (`docs/ADMINISTRATION.md`).
7. Publish the legal documents (Admin → Legal pages). Until then `/terms/*` shows a "not published" notice.
8. Watch `Content-Security-Policy-Report-Only` reports for a while, then set `CSP_ENFORCE_STRICT=true`.

**Rollback:** restore the backup, or run `php artisan migrate:rollback --step=N` for the phases you want to undo. Each phase's migration is listed in `REFACTORING_PLAN.md`. Rolling back the ledger tables discards the journal history.

## Environment reference (beyond Laravel defaults)
| Variable | Purpose |
|---|---|
| `APP_TIMEZONE` | Storage time zone (UTC recommended for new installs) |
| `PLATFORM_DOMAINS` | Hosts serving `/platform` (super-admin) |
| `TENANCY_FALLBACK_DEFAULT` | `false` when running more than one brand: unknown hosts get 404 |
| `TENANCY_DEFAULT_CURRENCY`, `…_SYMBOL` | Default currency for new casinos |
| `ADDONS_TRUSTED_KEYS`, `ADDONS_REQUIRE_SIGNATURE_BUNDLED` | Addon signature trust (`ADDONS.md`) |
| `DIGITOPAY_ALLOWED_HOSTS` | Must be set to DigitoPay's documented host before it can be used |
| `{PROVIDER}_CALLBACK_IPS`, `{PROVIDER}_CALLBACK_SECRET` | Game-provider callback authentication. **Fail closed** when empty. |
| `GOOGLE_FONTS_API_KEY` | Optional, for the font picker |
| `MFA_ENFORCE` | Staff two-factor authentication (default on) |
| `CSP_ENFORCE_STRICT`, `CSP_REPORT_URI` | Strict CSP enforcement and reporting |
| `RG_LIMIT_INCREASE_DELAY_HOURS` | Cooling-off period for raising deposit limits (default 24) |

Stripe and the PIX gateways are configured **per casino** in the admin panel. Their credentials are stored encrypted in `tenant_addons`, not in `.env`.

## Multi-brand
Create casinos in Platform → Tenants, which provisions the settings, the default theme and the addons. Point each casino's domains at the same application. See `TENANCY.md`.

## Verification after deploy
```bash
php artisan about
php artisan addon:package verify
php artisan integrations:status --all
php artisan wallet:reconcile
php artisan lang:check
```
