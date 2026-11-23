# Security Audit — Viper Pro

_Phase 0 findings and Phase 1 remediation. Status: **NOT approved for real-money operation.** Phase 1 produces a core fit for **controlled staging tests only** (see §6 for the release gates)._

Treat any existing deployment of the original code as **compromised**: anonymous users could credit their own wallets, take over the admin account and send gateway secrets to a third-party endpoint.

## 1. Mandatory actions for existing deployments

1. **Rotate every gateway and game-provider credential** ever saved in the admin panel. DigitoPay credentials in particular were sent to an anonymous AWS Lambda.
2. **Revoke the Google API key** `AIzaSyDQCZFgODu0jw7Ez00jgQU04SUsuncY3yQ` (it is still in git history).
3. Change the password of every admin account. The dump shipped `admin@demo.com` with a known hash and remember token. Invalidate sessions by rotating `APP_KEY` and `JWT_SECRET`.
4. Reconcile wallets against real gateway settlements. Deposits credited through forged callbacks are indistinguishable from real ones in the old code.
5. `public/logs.txt` (provider tokens and balances) was publicly served. It has been removed from the tree but remains in **git history**. Purging history (`git filter-repo`) is a repository-owner decision and was not done here.

## 2. Findings and resolution

Severity: **C** = exploitable anonymously for money or full account takeover; **H** = high; **M** = medium.

| # | Sev | Finding | Resolution (Phase 1) | Test |
|---|---|---|---|---|
| 1 | C | Payment callbacks (SuitPay, DigitoPay, MercadoPago, SharkPay, BsPay) credited wallets on an unauthenticated POST containing a status word. The transaction id was returned to the browser, so anyone could self-credit. | Callbacks are only hints. The status (and amount/currency where the API reports them) is fetched server-to-server. Settlement goes through `App\Core\Payments\DepositSettlementService` (row-locked, idempotent, pending-state check, amount/currency match). All attempts go to `callback_logs`. BsPay disabled. | `PaymentCallbackTest` |
| 2 | C | Stripe webhook had no signature check. `/stripe/publickey` returned **`stripe_secret_key`**. (Both routes were unmounted in this tree.) | `Webhook::constructEvent` with `STRIPE_WEBHOOK_SECRET` (fail closed, timestamp tolerance). Secret removed from the response. Transaction created pending, then settled idempotently. | `AdminAuthorizationTest::test_stripe_public_key…` |
| 3 | C | DigitoPay default endpoint was an anonymous AWS Lambda, so client ID/secret and payouts went to an unknown party. | Removed from the form default, the SQL dump and existing DB rows (migration). `App\Core\Security\TrustedEndpoint` only sends credentials to allowlisted HTTPS hosts (`config/payments.php`; DigitoPay empty, i.e. disabled, until the operator sets the official host). | `PaymentCallbackTest::test_digitopay_never_sends…` |
| 4 | C | Game-provider callbacks (`gold_api`, `venix_api`, `playigaming_api`, `ever`, `playgaming`, `cron/salsa/webhook`) were unauthenticated, with `user_code` = user id. Anyone could post wins to any account. | `AuthenticateProviderCallback` middleware (`provider.callback:<name>`): **fail closed** (503) until an IP allowlist and/or shared secret is configured per provider. Constant-time comparison. Logged. | `ProviderCallbackTest` |
| 5 | C | Password reset looked up the token without the email, so a token issued to the attacker reset any account. 5-character tokens, no expiry, no throttle, account enumeration. | 64-char CSPRNG token stored **hashed** and bound to the email. 60-minute expiry (`auth.passwords.users.expire`). Single use. Identical response for unknown emails. Throttled per IP and per email. Minimum length raised to 8. | `PasswordResetTest` |
| 6 | C | Game session tokens (`MakeToken`/`DecToken`) were base64 plus a fixed substitution table with no secret, so anyone could mint a session for any user. | `App\Core\Security\SignedToken`: HMAC-SHA256 with a purpose-derived key from `APP_KEY`, `exp`/`iat`/`jti`, constant-time verify. Legacy tokens rejected. TTL `GAME_SESSION_TTL` (4 h). | `GameSessionTokenTest` |
| 7 | C | `admin.filament` middleware discarded its own redirect (any logged-in user passed) and was bypassed when `APP_ENV=local`. `canAccessPanel` admitted affiliates to `/admin`. Withdrawal approve/cancel were **GET** links with no state check: replaying them paid out or refunded repeatedly. | Middleware rewritten (admin role required, no environment bypass). Per-panel access (affiliates never reach `/admin`). GET routes removed. Approve/cancel are confirmed Filament (Livewire POST + CSRF) actions calling `WithdrawalService`, a compare-and-set state machine (pending → processing → paid / back to pending; pending → cancelled + one refund). | `AdminAuthorizationTest`, `WithdrawalAndWalletTest` |
| 8 | H | Withdrawal and affiliate-withdrawal requests checked the balance and debited it separately (race, double spend). | `WithdrawalService::request`: one conditional `UPDATE … WHERE balance >= amount` plus record creation in a DB transaction. Exact cents parsing (`App\Core\Money\Amount`). | `WithdrawalAndWalletTest` |
| 9 | H | Financial tables were MyISAM, so transactions and row locks were no-ops. | Migration converts financial tables to InnoDB (MySQL). State transitions also use compare-and-set updates, which stay safe on any engine. | — (MySQL-only; run the migration in staging) |
| 10 | H | Wallet IDOR: `POST /api/profile/mywallet/{id}` activated any user's wallet. | Ownership enforced. | `WithdrawalAndWalletTest::test_player_cannot_activate…` |
| 11 | H | Spin wheel: unlimited anonymous spins, tokens reusable by every new account, out-of-range prize index. | Single-use redemption (`redeemed_at`, atomic claim). `random_int` within bounds. 5 spins/hour/IP. | `SpinWheelTest` |
| 12 | H | `AdvancedPage` extracted any uploaded ZIP over `base_path()` (RCE for any admin session, so anonymous RCE when combined with #5). | Disabled; the upload field was removed. A signed addon installer is planned for Phase 3. | — |
| 13 | H | Seeded admin account with a known hash and remember token in `sql/viperpro.sql`. | Row removed. `php artisan viper:create-admin {email}` (password prompted, min 12 characters). | — |
| 14 | M | Hardcoded Google API key; KaGaming HMAC key hardcoded as an empty string. | Key moved to env. KaGaming route unmounted and guarded (key-to-config refactor in Phase 7). | — |
| 15 | M | Unauthenticated `GET /clear` utility route. | Removed. | — |
| 16 | M | Raw exception messages returned to clients (auth, Stripe, Slotegrator). | Generic messages plus `report($e)`. | — |
| 17 | M | `APP_DEBUG=true`, `APP_ENV=local`, `LOG_LEVEL=debug` in `.env.example`. | Production-safe defaults. | — |
| 18 | M | No rate limiting anywhere. | `api` group 120/min; login 5/min per email+IP; registration 10/h/IP; reset 3/min/IP + 5/h/email; spin 5/h/IP. | `PasswordResetTest::test_*rate_limited` |
| 19 | M | Raw callback bodies written to the `debug` table. | Replaced by the redacting `callback_logs` audit table. | — |
| 20 | M | Registration always stored NULL affiliate defaults (fails on strict MySQL). | Read from the full settings row. | `SpinWheelTest` (registers users) |

## 3. Deceptive or non-compliant features

| Finding | Action |
|---|---|
| "Demo agent" accounts received a 90% win rate plus scripted wins, a mechanism for staging fake big-win streams. | Removed: every account gets identical odds. The flag still controls a separate demo balance; Phase 8 should remove the feature or label it unmistakably. |
| `/api/spin/winners` returned 200 Faker-generated names and a hardcoded "total bonus". | Now returns only real redemptions (names masked). |
| The in-house slot outcomes are picked from pre-set win/lose lists weighted by `games.rtp` (a hit frequency, not an RTP). Not a certified RNG. | The final pick now uses `random_int`. **These games must not be offered for real money** without independent RNG/game certification in the target jurisdiction. |

## 4. Remaining vulnerabilities and risks (open)

* **No ledger.** Balances are still mutable counters. Required before real money (Phase 2).
* **Provider transaction idempotency and rollback** are inconsistent per provider. Mitigated only by caller authentication (Phase 7, needs provider documentation).
* **No MFA or admin session management** (Phase 8).
* **Stored HTML/JS injection by admins** (`custom_js`, `custom_header`, `custom_body`, `custom_css` rendered with `{!! !!}`) is intentional operator functionality, but an admin-account compromise therefore means script running for all players. Mitigate with MFA, audit logging and a CSP (Phase 8).
* **Mass assignment:** `User::$fillable` includes `role_id`, `is_admin`, `is_demo_agent`, `banned` and the affiliate rates. Current public endpoints use explicit `only()` lists, but these must move to guarded or explicit fill (Phase 2).
* **JWT:** no refresh-token rotation or revocation list beyond the package default (Phase 2).
* **Social login and file uploads** are unaudited beyond the avatar `image|mimes` rule.
* **Git history** still contains the leaked key, the log and the seeded admin hash.
* **Unmounted code** (BsPay, KaGaming, Vibra, Slotegrator, Stripe routes) must go through review before being mounted.

## 5. Legal and licensing dependencies

* `public/originals/*` contains clients named after and modelled on commercial PG Soft titles. Distribution and operation need rights from the IP owner; otherwise remove them.
* Real-money operation requires a licence in each target jurisdiction, plus KYC, age verification, AML and responsible-gaming controls. None exist yet.

## 6. Release gates (must all pass before real money)

1. Phase 1 complete and regression tests green (**done**: `php artisan test`, 30 tests).
2. Financial tables on InnoDB in the target DB (run the Phase 1 migration; verify with `SHOW TABLE STATUS`).
3. Ledger plus reconciliation (Phase 2).
4. Per-provider callback authentication configured and tested in the provider's sandbox.
5. Gateway hosts confirmed against official documentation; credentials rotated.
6. Independent penetration test.
7. Licence, KYC, AML and responsible-gaming controls for the jurisdiction.
