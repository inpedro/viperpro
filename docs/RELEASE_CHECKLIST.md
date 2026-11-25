# Release Checklist and Security Sign-off

_Phase 9. "Verified here" means it was checked in this repository's test suite on SQLite. Everything else needs a human, a provider, or a staging environment._

## Automated (CI: `.github/workflows/ci.yml`)
| Check | State |
|---|---|
| Full test suite (`php artisan test`): security, ledger, payments contract, addons, tenancy isolation, localization, admin/RG | **verified here**: 123 tests, incl. a smoke test rendering every admin and platform page |
| Every migration reverses and reapplies to an identical schema (`MigrationRoundTripTest`) | **verified here** |
| Translations complete for en, sq, es, pt_BR (`lang:check --strict`) | **verified here** |
| Addon integrity (`addon:package verify`) | **verified here** |
| Frontend build | **verified here** |
| `composer audit` | **verified here: no advisories** (Laravel 12.74; was 81 on the original lock). Re-run on every release: CI reports new ones. |

## Must be done on staging (not possible in this environment)
- [ ] Run the suite against **MySQL/InnoDB** (row-locking semantics differ from SQLite)
- [ ] Concurrency/load test: parallel deposits, withdrawals and bets on one wallet; reconcile afterwards
- [ ] Tenant-isolation penetration test across two real domains (sessions, JWT `tid`, admin panels, storage)
- [ ] Independent penetration test (OWASP ASVS L2), including the admin custom-JS fields and the provider callbacks
- [ ] CSP report review → `CSP_ENFORCE_STRICT=true`
- [ ] Back-up and restore drill, plus rollback of the latest migration batch

## Provider and integration dependencies (blocked on external material)
- [ ] Each payment gateway (SuitPay, DigitoPay, Mercado Pago, SharkPay): confirm hosts, status vocabulary and signature scheme against **official documentation**, then test in their sandbox
- [ ] Stripe: written approval for gambling (restricted business) for the merchant account and jurisdiction
- [ ] Game providers: adapters on `GameProviderInterface` need each provider's integration docs and sandbox credentials (`INTEGRATIONS.md`)
- [ ] Sports data: a licensed provider to replace the third-party Firebase project
- [ ] Rotate all credentials listed in `SECURITY_AUDIT.md` §1; decide on purging git history

## Legal and compliance (operator)
- [ ] Gambling licence for every target jurisdiction
- [ ] KYC / age verification / AML provider integration (not implemented)
- [ ] National self-exclusion register integration where required
- [ ] Legal documents written by counsel and published per language (Admin → Legal pages)
- [ ] Rights to the `public/originals/*` game clients, or remove them. Independent RNG certification before any real-money use of in-house games.
- [ ] Native-speaker review of the Albanian (and Spanish/Portuguese) translations
- [ ] Rollover/wagering rules: the existing behaviour was preserved, including known defects (`LEDGER.md`). The operator must define the intended rules.

## Known functional gaps
* The wallet, profile, VIP, promotions and auth modals are not yet rebuilt on the design system.
* No CRM or segmentation.
* Domain events and unification of `role_id` with Spatie roles.

**Sign-off is not given by this document.** It lists what a security lead, the operator's compliance officer and legal counsel must each approve.
