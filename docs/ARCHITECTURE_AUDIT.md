# Architecture Audit — Viper Pro

_Phase 0 deliverable. Recorded 2026-11-23 against `main` @ `2b42044` (the repository has four commits; the first one contains the whole application)._

## 1. Stack as found

| Layer | Technology | Notes |
|---|---|---|
| Backend | Laravel 10, PHP ^8.1 | `composer.lock` pins `lcobucci/clock 2.3.0`, which refuses PHP 8.3 (install needs `--ignore-platform-req=php` or a lock update). _Fixed in Phase 9._ |
| Admin | Filament 3 (two panels: `/admin`, `/affiliate`) | Spatie roles `admin`, `afiliado`. |
| Player API | REST under `/api`, JWT (`tymon/jwt-auth`) guard `api` | Sanctum installed but only used by the default `/api/user` route. |
| Frontend | Vue 3 + Vite + Pinia + Tailwind + Flowbite, 84 `.vue` files | Single catch-all route `{view}` serves the SPA (`routes/groups/layouts/app.php`). |
| Realtime | Pusher / laravel-echo | |
| DB | MySQL. 68 migrations plus `sql/viperpro.sql` (2.1 MB dump) | 42 of 49 tables in the dump are **MyISAM** (no transactions, no row locks). |
| Modules | `nwidart/laravel-modules` installed; there is no `Modules/` directory | `modules_statuses.json` is empty. |
| Scheduler | `app/Console/Kernel.php::schedule()` is empty | There are no cron jobs; "cron" routes are just HTTP webhooks. |
| Tests | Only the Laravel example tests existed | |

## 2. Structural map

```
routes/web.php ──include──> routes/groups/{provider,gateways,auth,layouts}/*.php
routes/api.php ──include──> routes/groups/api/**            (auth.jwt for profile/wallet/missions)
app/Http/Controllers
  ├─ Api/*            thin-ish controllers for the SPA
  ├─ Gateway/*        5 near-identical gateway controllers (callbacks, withdrawals)
  ├─ Games/*          12 controllers for in-house slot clones (one per game)
  └─ Provider/*       Salsa / Slotegrator / Vibra
app/Traits/Gateways/*  6 traits, 270–340 lines each: HTTP calls + wallet mutation + bonus + affiliate logic
app/Traits/Providers/* 12 traits, 120–830 lines each: provider protocol + wallet mutation
app/Helpers/Core.php   1 588-line static "Helper" god-class (settings cache, wallet debits, tokens, formatting, uploads)
app/Filament/{Admin,Affiliate}
```

## 3. Findings

### 3.1 Business logic lives in traits and a static helper
* Each payment gateway carried its own copy of the deposit settlement routine (`finalizePayment`, `digitoPayFinalizePayment`, `finalizePaymentMP`, `finalizePaymentSharpay`, plus a BsPay variant). The copies had diverged: SharkPay *set* the deposit rollover where the others *incremented* it, and BsPay paid the bonus *instead of* the deposit.
* Provider traits mix protocol parsing, authentication (or the lack of it), wallet mutation and bonus/mission bookkeeping. They can't be tested in isolation.
* `Helper` (`App\Helpers\Core`) is used everywhere through the `\Helper` alias, which couples every layer to it.

### 3.2 Financial model
* `wallets` has one row per user with many balance columns (`balance`, `balance_bonus`, `balance_withdrawal`, `balance_deposit_rollover`, `balance_bonus_rollover`, `refer_rewards`, `vip_points`, `balance_demo`, …). There is no ledger. Balances change with `increment()`/`decrement()` from about 40 call sites.
* Amounts are passed around as PHP floats (`floatval`, `intval($amount)` in Stripe). DB columns are `decimal(20,2)`.
* `transactions` / `deposits` / `orders` are activity records, not double-entry postings. Nothing can reconcile wallets against them.
* There is a "currency" concept per wallet, but most code assumes a single wallet (`Wallet::where('user_id')->first()`) and one platform currency (`settings.currency_code`, default BRL).

### 3.3 Database
* Migrations didn't produce a working schema. `game_exclusives` was altered but never created; `users.avatar` was added twice; the permissions migration used Spatie v5 statics against v6; seven tables used in code existed only in the SQL dump. **Fixed in Phase 1** (see `REFACTORING_PLAN.md` §Phase 1).
* MyISAM on every money table made `DB::transaction()` and `lockForUpdate()` silently ineffective. **A Phase 1 migration converts the financial tables to InnoDB.**

### 3.4 Routing
* Route files were loaded with `include_once`, so routes registered only in the first application instance of a PHP process. That breaks the test suite, Octane and queue workers that boot the app more than once. **Fixed (`require`).**
* `VerifyCsrfToken::$except` covers every webhook prefix. That's correct for server-to-server callbacks, but only if those callbacks authenticate. They didn't (see the security audit).

### 3.5 Authorization model
* `User::canAccessPanel()` admitted both roles to both panels. `RedirectIfNotFilamentAdmin` discarded its own redirect, so it blocked no one. **Fixed.**
* A custom `User::roles()` overrode Spatie's polymorphic relation, so `assignRole()` could not write `model_type`. **Fixed.**
* `users.role_id` (0 = admin, 3 = default) coexists with Spatie roles. Notifications use `role_id = 0`; access uses Spatie. This needs to be unified in Phase 2.

### 3.6 Frontend
* Vue 3 SPA. Strings go through `vue-i18n`-style `$t()` with `lang/{en,es,pt_BR}.json`, but many components and every Filament page contain hardcoded Portuguese.
* `public/originals/*` contains twelve self-hosted slot clients named after commercial PG Soft titles (Fortune Tiger, Fortune Ox, …). See `SECURITY_AUDIT.md` §5. These must not be distributed or used without a licence.

### 3.7 Brazil coupling (input to Phase 5)
| Signal | Count |
|---|---|
| Files referencing PIX | 32 |
| Files referencing CPF | 17 |
| Files referencing BRL / `R$` | 19 |
| Default locale / timezone | `pt_BR` / `America/Sao_Paulo` (`config/app.php`) |
| Validation | `laravellegends/pt-br-validator` (`cpf_ou_cnpj` rules in withdrawal) |

Affiliate withdrawals hardcode `currency => 'BRL', symbol => 'R$'`. **Fixed in Phase 1** to use the wallet currency, because that one was a financial-correctness issue.

## 4. Architectural bottlenecks (ranked)

1. No ledger. Balances aren't auditable or reconcilable (blocks real-money operation).
2. Provider and gateway logic is duplicated across traits with no contracts (blocks the addon ecosystem).
3. Single-tenant by construction: `Setting::first()`, `Gateway::first()`, `GamesKey::first()` and global cache keys (`setting`, `custom`) everywhere (blocks white-label).
4. The static `Helper` god-class (blocks testability).
5. Hardcoded locale and currency assumptions (blocks internationalization).

## 5. Decisions recorded

* **Tenancy strategy** (decision for Phase 4): shared database with an enforced `tenant_id` global scope, plus per-tenant encrypted settings. Rationale: the code reads singleton settings rows; converting them to tenant-scoped rows is a mechanical change, while database-per-tenant would need connection switching in every queue job and webhook. Webhooks resolve the tenant from the domain and the callback route, never from client input. Large operators can be moved to a dedicated database later, behind the same `TenantResolver` contract.
* **Ledger** (Phase 2): an append-only `ledger_entries` table (double entry: player wallet account ↔ platform clearing account) with integer minor units. `wallets.*` balance columns become a cached projection with a reconciliation command.
* **Addons** (Phase 3): Laravel service-provider packages with a validated `addon.json` manifest, installed through Composer from a trusted registry. No runtime ZIP extraction (the former `AdvancedPage` updater was removed for this reason).
