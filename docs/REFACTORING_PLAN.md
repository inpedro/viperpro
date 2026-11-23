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

## Phase 3: Addon framework
`addon.json` manifest validated against a JSON Schema (id, version, category, `requires.platform` semver, permissions, config schema, migrations path, health check class). Addons are Composer packages exposing a service provider and installed from a signed, trusted registry, never from uploaded archives. An `AddonManager` (enable/disable, dependency resolution, per-tenant activation, migration runner, health) plus a Filament addon manager page. The first addons are the existing gateways and providers, re-packaged from their Phase 2 adapters.

## Phase 4: Multi-tenancy and white-label
Shared DB + `tenant_id` + global scope (decision in `ARCHITECTURE_AUDIT.md` §5). `TenantResolver` by domain; tenant-aware auth guard, cache prefixes, storage prefixes, queue payloads, webhook routes (`/t/{tenantRouteKey}/…` or a per-domain callback URL). Singleton rows (`settings`, `gateways`, `games_keys`, `custom_layouts`) become per-tenant with encrypted secret columns. A Super Admin panel is separated from the Tenant Admin panel. Tenant-isolation test suite.

## Phase 5: Internationalization
English default; Albanian (`sq`) translations; locale files for the existing `es`/`pt_BR`. Move every hardcoded string (Filament and Vue) to translation keys. RTL support. Configurable phone (libphonenumber), address and registration fields. PIX/CPF become an optional Brazil addon (field set + validator + gateways). Locale-aware money and date formatting. Timezone per tenant.

## Phase 6: Frontend redesign
Design tokens per tenant; themes Obsidian / Royal / Minimal / Neon; lobby, player dashboard, wallet, promotions, VIP, affiliate, responsible-gaming pages. Remove the cloned game assets unless licensed.

## Phase 7: Integrations ecosystem
Rebuild each provider and gateway adapter against its official documentation and sandbox: signed webhooks, idempotent bet/win/rollback with ledger references, health checks, webhook monitoring and a replay UI over `callback_logs`. New adapters (e.g. Adyen, Nuvei, licensed aggregators) only with real documentation and credentials.

## Phase 8: Administration and analytics
MFA for admins, admin session management, audit log for every admin action, CSP, a reconciliation dashboard, CRM, analytics, removal/labelling of demo-agent accounts, and responsible-gaming tooling (limits, self-exclusion, reality checks).

## Phase 9: QA and release
Full regression, tenant-isolation, load and penetration tests; staging deployment on MySQL/InnoDB; documentation review; legal sign-off.

## Working assumptions
* Tests run on SQLite in memory (`phpunit.xml`). Engine-specific behaviour (InnoDB locks) must also be verified on staging MySQL.
* `composer install` currently needs `--ignore-platform-req=php` on PHP 8.3 because of `lcobucci/clock 2.3.0`; updating `tymon/jwt-auth`/`lcobucci/*` is part of Phase 2.
