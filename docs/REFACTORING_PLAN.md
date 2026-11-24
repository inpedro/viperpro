# Refactoring Plan — Viper Pro → modular white-label platform

Follows the phase order in the Master Transformation Directive (§22):
**security → financial integrity → core architecture → multi-tenancy → addon ecosystem → internationalization → design → integrations → QA.**
Each phase ends with the report format from §24 and must leave `php artisan test` green.

## Phase 0: Repository audit (**done**)
Deliverables: `ARCHITECTURE_AUDIT.md`, `SECURITY_AUDIT.md`, `INTEGRATION_INVENTORY.md`, this plan.

## Phase 1: Critical security remediation (**done; staging only**)

| Item | Implementation |
|---|---|
| Payment callback authentication | Server-to-server verification in each gateway trait (`suitpayFetchStatus`, `digitoPayFetchStatus`, `mpVerifyAndSettle`, `sharkpayVerifyAndSettle`); Stripe signature verification |
| Provider webhook authentication | `App\Http\Middleware\AuthenticateProviderCallback`, `config/game_providers.php` (fail closed) |
| Password reset | `AuthController::submitForgetPassword/submitResetPassword` |
| Signed game sessions | `App\Core\Security\SignedToken` behind `Helper::MakeToken/DecToken` |
| Admin authorization | `RedirectIfNotFilamentAdmin`, `User::canAccessPanel`, Filament withdrawal actions |
| Withdrawal security | `App\Core\Payments\WithdrawalService` (CAS state machine) |
| Atomic wallet ops / idempotency | `App\Core\Payments\DepositSettlementService`, unique `transactions.payment_id`, InnoDB migration |
| Exact money parsing | `App\Core\Money\Amount` (integer minor units, no float, no bcmath) |
| Remove Lambda endpoint | form, dump, DB migration, `TrustedEndpoint` allowlist |
| Secrets and public logs | Google key → env, `public/logs.txt` removed, admin seed removed, `.env.example` hardened |
| Fresh-install repair | baseline migration, Spatie v6 pivot fix, duplicate `avatar` column, `include_once` → `require` |
| Audit trail | `callback_logs` table + `App\Core\Security\CallbackLog` |
| Tests | `tests/Feature/Security/*` (29 security tests + homepage smoke test) |

**Breaking changes**
* Legacy game tokens are invalid (players relaunch games).
* Game-provider callbacks return **503 until configured** (`<PROVIDER>_CALLBACK_IPS` / `_CALLBACK_SECRET` / `_CALLBACK_SECRET_SOURCE`).
* DigitoPay is disabled until `DIGITOPAY_ALLOWED_HOSTS` is set.
* Stripe webhooks need `STRIPE_WEBHOOK_SECRET`.
* Withdrawal status `3` = processing (transient).
* Public `GET …/withdrawal/{id}` routes no longer exist.
* Password minimum length is 8 (was 6) on reset.
* The `/api/spin/winners` payload contains real data only.

**Rollback path:** every change is in normal commits on the transformation branch. The migration's `down()` drops `callback_logs`, the spin redemption columns and the unique index. The InnoDB conversion and Lambda removal are intentionally not reverted.

## Phase 2: Core architecture and ledger (**done, see status below**)

| Item | Status |
|---|---|
| Append-only double-entry ledger, integer minor units, idempotency, immutability | done (`app/Core/Ledger`, `docs/LEDGER.md`) |
| `WalletService` as the explicit writer; deposits, bonuses, CPA, withdrawals, spin bonus and bet debits migrated | done |
| Remaining legacy call sites journaled by `WalletLedgerObserver` (exact DB deltas, labelled by `LedgerContext`) | done; migration of each site is ongoing (list in `docs/LEDGER.md`) |
| `wallet:reconcile [--open]` | done |
| `Money` value object | done (2-decimal precision, see limits) |
| `PaymentGatewayInterface` + adapters (SuitPay, DigitoPay, Mercado Pago, SharkPay), registry, thin controllers | done; Stripe/BsPay adapters pending |
| `GameProviderInterface` + fail-closed descriptors; `integrations:status` | done; full provider adapters are Phase 7 |
| Privileged `User` fields removed from `$fillable` | done |
| Domain events (`DepositSettled`, …) with bonus/VIP/affiliate as listeners | **open** |
| Unify `users.role_id` with Spatie roles | **open** |
| JWT refresh rotation / revocation; `lcobucci` lock update for PHP 8.3 | **open** |
| Characterisation tests + rewrite of the `payWithRollover` rules | **open** (needs product sign-off on intended rules) |

**Breaking changes in Phase 2**
* `POST /api/wallet/deposit/payment` returns 422 for gateways that aren't installed or configured (previously any name was accepted).
* `User::$fillable` no longer includes privileged fields. Code that set them with `create()`/`update()` must use `forceFill()`; the in-repo callers are updated.
* `SuitPayController`/`DigitoPayController`/`MercadoPagoController`/`SharkPayController` no longer expose the legacy `confirmWithdrawal*`/`cancelWithdrawal*` methods; payouts go through `WithdrawalActions` → `PaymentGateways`.
* Removed trait methods: `consultStatusTransaction` (SuitPay), `digitoPayConsultStatusTransaction`, `consultStatusTransactionMP`, `mercadoPagoCallback`, `consultStatusTransactionSharkpay`.

**Migration:** `2026_11_24_000001_create_ledger_tables` (rollback drops the three tables). After deploying, run `php artisan wallet:reconcile --open`.

## Phase 3: Addon framework (**done**)

| Item | Status |
|---|---|
| Validated `addon.json` manifests, semver, platform and addon dependencies | done |
| Integrity (`addon.lock`) + Ed25519 signatures (`addon.sig`), trusted keys, no symlinks | done |
| Lifecycle: install / enable / disable / update / uninstall (data kept unless `--purge`), migrations, permissions | done |
| Encrypted, masked, validated addon configuration | done |
| Boot loader (PSR-4 + providers) with per-boot lock-hash tamper check | done |
| Admin addon manager page (state, verification problems, health, configure) | done |
| `audit_logs` table + `Audit::record()` | done |
| Payment gateways (SuitPay, DigitoPay, Mercado Pago, SharkPay) as independent addons | done |
| Scheduled `wallet:reconcile` and `addon:package verify` | done |
| Stripe and BsPay as addons | **open** (Stripe routes are unmounted; BsPay disabled) |
| Game providers as addons (`contributes.game_providers`) | Phase 7 (needs real adapters) |
| Per-tenant addon activation | Phase 4 |

Full documentation: `docs/ADDONS.md`. **Breaking:** run `php artisan addon:sync --enable` after deploying, or no payment gateway is available.

Also fixed in Phase 3:
* Deposit amount validation enforced `max:<min_deposit>` without `numeric`. That was a string-length check, so there were effectively no deposit limits; it now enforces min and max.
* The DigitoPay manual payout page called the cash-out without the payment-record ID it requires.

## Decision log
* **Rollover rules (`Helper::payWithRollover`) are preserved exactly**, including their latent bugs: decimal-string `=== 0` comparisons never match, so some rollover-completion conversions never fire. Changing them changes player payouts and needs explicit product sign-off. Until then they are journaled as `game.settlement` and listed in `docs/LEDGER.md`.

## Phase 4: Multi-tenancy and white-label (**done, core**)

| Item | Status |
|---|---|
| Shared-DB tenancy: `tenants`, `tenant_domains`, `tenant_id` on 36 tables, backfill to tenant #1 | done |
| Global scope (fail closed), stamped writes, foreign/moved `tenant_id` refused | done |
| Domain resolution, suspended tenants, unknown hosts, platform domains | done |
| Tenant-aware auth (scoped users, JWT `tid`, per-tenant password resets), panels, cache, uploads, webhooks, ledger, reconcile | done |
| Per-tenant addon activation and encrypted credentials | done |
| Platform panel: casinos (domains, suspend/activate, create admin), platform addons, audit log | done |
| Tenant provisioning (settings, layout) | done |
| Tenant isolation test suite | done (15 tests) |
| Legal pages, SEO, SMS per tenant; subscription plans/billing | **open** (Phases 5, 6, 8) |
| Spatie teams (per-tenant custom roles) | **open** |

**Breaking changes**
* Unknown hosts are served as tenant #1 only while `TENANCY_FALLBACK_DEFAULT=true` (the default).
* Users' e-mail uniqueness is per tenant.
* JWTs issued before this release have no `tid` claim and are rejected (players log in again).
* Password-reset rows moved to `tenant_password_resets`.
* Addon credentials are per tenant (the existing configuration was copied to tenant #1).
* Uploads go under `tenants/{id}/uploads` (existing files keep their paths).
* Fixed a typo in `create_vip_users_table` (FK to `´vips`) that broke fresh MySQL installs.

**Migrations:** `2026_11_26_000001_add_multi_tenancy` and `2026_11_26_000002_create_tenant_addons_table`. Both are reversible (tested up → down → up on SQLite). Full detail in `docs/TENANCY.md`.

## Phase 5: Internationalization (**done, player-facing**)

| Item | Status |
|---|---|
| English default, UTC storage default, locale registry with native names, ICU locale and direction | done |
| Per-tenant enabled locales, default locale and display timezone; admin page | done |
| Locale resolution (player, Accept-Language, tenant) and validated language updates | done |
| Albanian (`sq`): 100% of the JSON strings and Laravel validation/auth/password messages | done (needs native review) |
| Hardcoded Portuguese API messages → English keys in all locales (`pt_BR` keeps the original wording) | done |
| RTL support (`<html dir>`, locale `dir`, data-driven selector) | done |
| Locale-aware money, number and date formatting | done |
| International phone; configurable national-ID field (CPF only when chosen) | done |
| `lang:check --strict` completeness check (in the test suite) | done |
| Filament admin labels (~290), Vue literals (10 files) | **Phase 8 / Phase 6** |

**Breaking changes**
* **Existing installs must set `APP_TIMEZONE=America/Sao_Paulo`**, or stored timestamps shift.
* API messages now default to English unless the tenant/player locale says otherwise. Tenant #1 keeps `pt_BR` as its default.
* `updateLanguage` returns 422 for locales the casino doesn't offer.
* The registration password minimum is 8 characters (was 6).
* Registered phone numbers are stored normalized (E.164 with `+` when given).

Also fixed: registration validated `unique:users` across all tenants, which blocked the same e-mail at another brand and revealed that the account existed there. It is now unique per tenant.

**Migration:** `2026_11_27_000001_add_localization_settings` (reversible). Full detail in `docs/LOCALIZATION.md`.

## Phase 6: Frontend redesign (**done: design system, themes, lobby**)

| Item | Status |
|---|---|
| Design tokens + four themes (Obsidian, Royal, Minimal, Neon), per-tenant selection with safe overrides, admin page with preview | done |
| Existing pages adopt the theme through the legacy variable mapping; legacy mode for un-themed tenants | done |
| Design-system components (`resources/js/Components/DS`) | done |
| New lobby homepage: hero, search, categories, providers, featured/popular/new rails, grid, URL filters, responsible-gaming notice | done |
| Normalized lobby API | done |
| Remaining Portuguese UI strings, `R$` amounts → translations + `Intl` money formatting | done |
| Screenshots of all themes (`docs/screenshots`) | done |
| Wallet/deposit/withdraw, profile, VIP, promotions, auth modals rebuilt on the design system | **open** (with Phase 8) |
| Legal pages as tenant-managed content | done (Phase 8) |

**Breaking changes**
* `/` serves the new lobby; the old homepage is at `/classic`.
* Tailwind uses `darkMode: 'class'`: the theme decides light/dark instead of the visitor's OS.
* The sports bets feed and the affiliate rules no longer show fabricated content.

**Migration:** `2026_11_28_000001_add_theme_to_custom_layouts` (reversible). Details in `docs/THEMES.md`.

## Phase 7: Integrations ecosystem (**done for payments; game providers blocked on provider documentation**)

| Item | Status |
|---|---|
| Stripe addon (Checkout, signed webhooks, tenant binding, refunds with ledger reversal), frontend redirect flow | done |
| Unified `POST /payments/webhooks/{gateway}`; `WebhookResult` (credited / acknowledged / rejected) | done |
| Payout methods declared by gateways; withdrawal API and payouts follow them | done |
| Contract test harness for every gateway (`GatewayContractTest`) + fake provider clients | done |
| Integrations admin page: health + webhook monitor; `integrations:status --all` hourly | done |
| Boot-order bug: addons never loaded in real processes; fixed + process-level regression test | done |
| Dead Stripe code removed (unmounted controller, trait, service, route files) | done |
| Game provider adapters on `GameProviderInterface` (bets/wins/rollbacks via `WalletService`) | **blocked:** needs each provider's documentation and sandbox |
| Sports data from a licensed provider (replacing the third-party Firebase project) | **blocked:** needs a provider contract |

**Breaking changes**
* `PaymentGatewayInterface::processWebhook()` returns `WebhookResult` and gains `getPayoutMethods()`.
* The PIX addons are now 1.1.0: run `php artisan addon:sync`.
* Withdrawal `type` must be one of the default gateway's payout methods or `bank`.
* Authentic-but-not-creditable callbacks (already settled, amount mismatch) now return 200.
* `stripe/session` and `stripe/publickey` are gone; Stripe deposits use `wallet/deposit/payment`.

No new migrations. Full detail in `docs/INTEGRATIONS.md`.

## Phase 8: Administration and analytics (**done, core**)

| Item | Status |
|---|---|
| TOTP MFA for admin/super-admin (enrol, recovery codes, replay protection), per-session challenge | done |
| Staff session list + revocation (admin and platform) | done |
| Audit of every Filament create/update/delete (field names only) + security events | done |
| Security headers; baseline CSP enforced, strict CSP Report-Only | done |
| JWT revocation (`tokens_valid_after`) on password reset and self-exclusion | done |
| Responsible gaming: deposit limits with cooling-off, self-exclusion, reality checks, enforced server-side | done |
| Operator-managed, sanitized, localized legal pages (old template text removed) | done |
| Ledger-based analytics per casino and platform-wide; found and fixed a query bug (`pluck(DB::raw)`) via tests | done |
| Demo-agent accounts labelled "Demo account (play money)" | done |
| Reconciliation dashboard (UI on top of `wallet:reconcile`) | **open** (CLI exists) |
| CRM / player segmentation / marketing suppression | **open** |
| Filament admin labels still Portuguese (~290) | **open** |
| Wallet/profile/VIP/promotions/auth modals on the design system | **open** (carried from Phase 6) |

**Breaking changes**
* Admins and super-admins must enrol TOTP on their next panel visit (`MFA_ENFORCE=false` disables this; not recommended).
* CSP and security headers on every response. Custom JS that loads plugins (`<object>`/`<embed>`) or a `<base>` tag stops working; other sites can no longer frame the casino.
* `/terms/*` pages show the operator's published legal documents. Until they're published they show a "not published yet" notice instead of the old Portuguese template.
* Tokens issued before a password reset or self-exclusion are rejected.
* Deposits can be refused with 403 (limit or self-exclusion); excluded players can't launch games.

**Migration:** `2026_11_29_000001_phase8_admin_security_rg_legal` (reversible: drops the three new tables and the new user columns). Details in `docs/ADMINISTRATION.md` and `docs/RESPONSIBLE_GAMING.md`.

## Phase 9: QA and release
Full regression, tenant-isolation, load and penetration tests; staging deployment on MySQL/InnoDB; documentation review; legal sign-off.

## Working assumptions
* Tests run on SQLite in memory (`phpunit.xml`). Engine-specific behaviour (InnoDB locks) must also be verified on staging MySQL.
* `composer install` currently needs `--ignore-platform-req=php` on PHP 8.3 because of `lcobucci/clock 2.3.0`; updating `tymon/jwt-auth`/`lcobucci/*` is part of Phase 2.
