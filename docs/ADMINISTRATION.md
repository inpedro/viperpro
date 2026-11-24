# Administration, Staff Security and Analytics

_Phase 8. See also `SECURITY_AUDIT.md`, `TENANCY.md`, `RESPONSIBLE_GAMING.md`._

## Two-factor authentication (TOTP)
* Required for the `admin` and `super-admin` roles in the **admin** and **platform** panels (`config/security.php`, `MFA_ENFORCE`). Other staff (affiliates) can enrol voluntarily at `/affiliate/mfa`.
* On the first visit the staff member is sent to `{panel}/mfa`, scans the key (RFC 6238, SHA-1, 6 digits, 30 s) and confirms a code. They then get **8 single-use recovery codes**, shown once and stored hashed.
* Every new session must pass the challenge. Codes can't be replayed: the last accepted time step is stored.
* The TOTP secret is encrypted at rest (`APP_KEY`). **Rotating `APP_KEY` invalidates enrolments.**
* **Lost device and no recovery codes:** a platform super-admin clears `mfa_secret`/`mfa_enabled_at` for the user (an audited action), and the user enrols again.

## Staff sessions
`Admin → Staff sessions` (and the platform equivalent) lists each staff session: user, panel, IP, browser, last seen and MFA status. **Revoke** logs that session out on its next request. Sessions are identified by `sha256(session id)`; the raw id is never stored.

## Audit log
* Every create, update or delete performed through a Filament panel is recorded as `admin.created` / `admin.updated` / `admin.deleted`. The record holds the model, its id and the **names** of the changed fields, never their values, so secrets and personal data stay out of the log.
* Explicit security events are recorded too:
  * MFA enabled, disabled or failed, and recovery codes used (`security.mfa_*`);
  * session revocation (`security.session_revoked`);
  * responsible-gaming changes (`rg.*`);
  * the addon and tenant actions recorded since Phases 3–4.
* Platform → Audit log shows every tenant's entries. The UI offers no edit or delete.

## Security headers
`SecurityHeaders` (global middleware) sends:
* `X-Content-Type-Options: nosniff`;
* `Referrer-Policy: strict-origin-when-cross-origin`;
* a restrictive `Permissions-Policy`;
* `X-Frame-Options: SAMEORIGIN`;
* HSTS over HTTPS;
* a **baseline CSP that is always enforced** (`object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action …`).

A **strict CSP** is sent as `Content-Security-Policy-Report-Only` until each casino's custom JS and the providers' game iframes are inventoried. Once reports are clean, set `CSP_ENFORCE_STRICT=true`; `CSP_REPORT_URI` sets where reports go.

## Player token revocation
JWTs issued before `users.tokens_valid_after` are rejected. The column is set by a password reset and by self-exclusion.

## Legal pages
* `Admin → Legal pages` holds the per-casino, per-language Markdown documents: terms, privacy, responsible-gaming, bonus, welcome-bonus, referral, cookies, aml-kyc.
* HTML is produced server-side with raw HTML stripped and unsafe links removed.
* `GET /api/legal/{slug}` serves the visitor's language, then the casino's default language, then any published language. If nothing is published it returns `published: false`.
* **The platform ships no legal text.** The old Portuguese template pages under `/terms/*` were removed. The same URLs now show the operator's documents. Legal counsel for each jurisdiction must supply the text.

## Analytics
`Admin → Analytics` (one casino) and `Platform → Analytics` (every casino) are computed from the **ledger**, the same source reconciliation checks. Figures are given per currency and never converted:

| Metric | Definition |
|---|---|
| Deposits | player-side credits of `deposit` journals |
| Withdrawals | withdrawal requests minus cancellations |
| GGR | net movement of `platform:game:*` (bets − wins) |
| Bonus cost | payouts from `platform:bonus:*` |
| Active players | distinct wallets with a `game.bet` journal |
| Registrations | users created in the period |

Legacy balance changes that were only *observed* by the ledger safety net are included with their coarse type, so GGR excludes legacy game settlements not yet journaled as `game.*` (see `LEDGER.md`). Reconcile before relying on the figures (`php artisan wallet:reconcile`).

## Demo accounts
The `is_demo_agent` flag (formerly labelled "Influencer") now reads **Demo account (play money)**, with an explanation in the form and a column in the user list.
* These accounts play only with `balance_demo`, under the same odds as everyone else (rigging was removed in Phase 1).
* Their wins never enter game history or public feeds.
* Operators must make sure anyone streaming with one discloses it; that obligation can't be enforced in code.
