# Multi-Tenancy and White-Label

_Phase 4. Code: `app/Core/Tenancy`, `app/Http/Middleware/ResolveTenant.php`, `app/Filament/Platform`._

## Architecture decision

**Shared database, `tenant_id` on every tenant-owned table, enforced by an Eloquent global scope.**

The application reads "singleton" rows everywhere (`Setting::first()`, `Gateway::first()`, `CustomLayout::first()`). With a scope, these become per-tenant without touching their roughly 100 call sites. Database-per-tenant would need connection switching in every webhook, command and job, and per-tenant migrations. The contract (`Tenancy`, `ResolveTenant`) allows moving a large operator to a dedicated database later.

| Concern | How |
|---|---|
| Tenant resolution | `ResolveTenant` (global middleware): `tenant_domains.domain` = request host. **Never** from headers, query or body. |
| Unknown host | 404, or the default tenant when `TENANCY_FALLBACK_DEFAULT=true` (the single-brand default) |
| Suspended tenant | 503 on all of its domains |
| Reads | `TenantScope`: `tenant_id = current`. No context resolved → **no rows** (fail closed). Platform context → only `tenant_id IS NULL`. |
| Writes | `BelongsToTenant` stamps `tenant_id`. Creating a row for another tenant, or changing `tenant_id`, throws. `tenant_id` is not mass-assignable. |
| Uniqueness | per tenant: user e-mail, ledger account codes, ledger idempotency keys. Gateway `payment_id` stays globally unique. |
| Auth | User lookups are scoped, so credentials from brand A don't exist on brand B. JWTs carry `tid` and are rejected on any other tenant's domain. Password resets live in `tenant_password_resets`. |
| Admin panels | `/admin` and `/affiliate` admit only the casino's own staff, on its own domain. `/platform` admits only `super-admin` users with no tenant, on `PLATFORM_DOMAINS`. |
| Cache | `Tenancy::key()` prefixes every tenant cache key (settings, layout, spin prizes) |
| Files | `Helper::upload` and every Filament `FileUpload` store under `tenants/{id}/uploads` |
| Webhooks | the tenant is resolved from the callback's domain like any request. A deposit belonging to brand A is invisible, and so unsettleable, on brand B's callback URL. Give each brand its own callback domain when registering it with providers. |
| Ledger | accounts, journals and entries are tenant-owned, so each brand has its own platform accounts and reconciliation |
| Addons | code is installed platform-wide (`addons`). Each tenant activates available addons and has its **own encrypted credentials** (`tenant_addons`). |
| Console / scheduler | starts in the default tenant. Commands that cover all brands iterate with `Tenancy::runAs()` (for example `wallet:reconcile`). |
| Queue jobs | none exist yet. A job must capture `Tenancy::id()` when dispatched and run its `handle()` inside `Tenancy::runAs()`. |
| Cross-tenant code | only via `Tenancy::acrossTenants()` (reconciliation, platform reporting); never in request handling |

Shared (not tenant-owned): the game catalogue (`games`, `providers`, `categories`, `category_game`, `vibra_casino_games`), `currencies`, roles and permissions, `addons` (installation), `tenants` and `tenant_domains`.

## Per-tenant identity
Each tenant has its own `settings` row (name, logos, favicon, currency, limits, bonus and affiliate rules), its own `custom_layouts` row (colours, fonts, custom CSS/JS), `setting_mails`, banners, VIP levels, missions, spin configuration, gateway credentials and addon activation. A new tenant is provisioned with neutral defaults (`TenantProvisioner`), which the tenant admin then edits in `/admin`.

Not yet per-tenant (later phases): legal pages and SEO metadata (Phase 5/6 content model), SMS configuration, and theme selection beyond `custom_layouts` (Phase 6).

## Operating
```bash
php artisan migrate                                   # backfills existing data into tenant #1
php artisan viper:create-admin root@example.com --platform   # platform super admin
# PLATFORM_DOMAINS=platform.example.com ; open https://platform.example.com/platform
# Platform → Casinos → New: name, slug, domains → "Create admin" for the casino
# Platform → Addons: install/enable addons platform-wide; each casino activates them in /admin → Addons
```

When running more than one brand set `TENANCY_FALLBACK_DEFAULT=false`, so an unconfigured host can never show brand #1.

## Isolation tests
`tests/Feature/Tenancy` covers:
* scoped reads, including the same e-mail registered in two brands;
* foreign and moved `tenant_id` refused;
* JWT and credentials not portable between brands;
* a callback on brand B unable to settle brand A's deposit;
* per-tenant ledgers, settings cache and addon credentials;
* unknown and suspended hosts refused;
* no context returning no rows;
* panel access bound to tenant and platform;
* the tenant admin page unable to perform platform actions.

## Limits
* Tests run on SQLite. On MySQL the backfill default on `tenant_id` is dropped after migration, so raw inserts that forget `tenant_id` fail loudly. Verify on staging.
* Spatie roles are global names. Access is still tenant-bound, because users are tenant-owned and panel access checks the user's tenant, but per-tenant custom roles (Spatie "teams") are not enabled.
* The `/platform` panel needs a dedicated domain. Without `PLATFORM_DOMAINS`, it is unreachable by design.
