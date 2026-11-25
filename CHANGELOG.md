# Changelog

## 2.0.0-rc.1 (unreleased: staging only, see `docs/RELEASE_CHECKLIST.md`)

The original Viper Pro rebuilt into a secure, modular, multi-tenant, international platform. Each phase has its own commits, migrations and documentation. `docs/REFACTORING_PLAN.md` lists every breaking change.

### Security (Phases 1, 8, 9)
* Payment callbacks no longer credit balances on their own: status is re-verified with the provider, settlement is atomic and idempotent, and amount and currency are checked.
* Game-provider callbacks are authenticated and fail closed.
* Game session tokens are signed; password reset is fixed; admin authorization is enforced.
* Removed: exposed secrets, the anonymous DigitoPay Lambda default, the ZIP-upload code-execution path, the public log file, and demo-agent rigging.
* Staff TOTP MFA, staff session revocation, audit of every admin change, CSP and security headers, JWT revocation.
* Upgraded to **Laravel 12** (PHP 8.2+); `composer audit` is clean (81 advisories on the original lock, including a critical Livewire RCE).
* Casino admins can no longer rewrite the shared `.env` (name/SMTP of every casino, variable injection); SMTP passwords are encrypted.

### Core (Phase 2)
* Append-only double-entry ledger, `WalletService`, reconciliation command, `Money` value object.
* Payment gateway and game provider contracts.

### Addons (Phase 3)
* Manifest + lock-file integrity + Ed25519 signatures. Payment gateways are independent addons. Admin addon manager.

### Multi-tenancy (Phase 4)
* Shared database with an enforced `tenant_id` scope, host-based resolution, platform console, tenant provisioning, and per-tenant addons with encrypted credentials.

### Localization (Phase 5)
* English (default), Albanian, Spanish, Brazilian Portuguese; ICU formatting; configurable phone and document fields; Brazil-specific defaults removed.

### Admin localization
* The admin, affiliate and platform panels are translated into English, Albanian, Spanish and Brazilian Portuguese. Admin amounts use each casino's currency.

### Frontend (Phase 6)
* Design system, four themes (Obsidian, Royal, Minimal, Neon), new lobby, normalized lobby API. Fabricated winners, feeds and statistics removed.

### Integrations (Phase 7)
* Stripe addon (Checkout, signed webhooks, refunds), unified webhook endpoint, contract-test harness for every gateway, integrations monitor.

### Administration and player protection (Phase 8)
* Deposit limits, self-exclusion and reality checks, enforced server-side. Operator-managed legal pages. Ledger-based analytics.

### Release engineering (Phase 9)
* CI workflow, migration round-trip test (and fixes to 21 migrations whose `down()` was broken), deployment guide, release checklist.

### Not included (blocked on external material)
Game-provider adapters, a licensed sports data feed, KYC/AML, and gateway sandbox verification. See `docs/RELEASE_CHECKLIST.md`.
