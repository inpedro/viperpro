# Responsible Gaming Tools

_Phase 8. These are common regulatory practice, **not** a certification for any jurisdiction. Each licence sets its own rules, and the operator must confirm them, including limit periods, cooling-off times, exclusion registers (e.g. national schemes) and reality-check intervals._

| Tool | Behaviour | Enforced at |
|---|---|---|
| Deposit limits (daily / weekly / monthly, rolling 1/7/30 days) | Lowering or setting a limit applies **immediately**. Raising or removing one applies after `RG_LIMIT_INCREASE_DELAY_HOURS` (24 h by default). **Pending** deposits count toward the limit, so a burst of requests can't bypass it. | `POST /api/wallet/deposit/payment` (server side, 403 with the reason) |
| Take a break / self-exclusion (24 h, 7 d, 30 d, 6 m, 1 y, permanent) | Requires explicit confirmation. **Cannot be shortened or lifted** by the player. All of the player's sessions end immediately (`tokens_valid_after`). Withdrawals stay available. | deposits, game launch (`GET /api/games/single/{id}`), in-house game spins |
| Reality check | Reminder every 15–240 minutes (player's choice), showing the elapsed time, with **Continue** or **Take a break** options. | SPA (`VpRealityCheck`) |

## API (authenticated, `/api/profile/responsible-gaming`)
| Method | Path | Body |
|---|---|---|
| GET | `/` | — (current limits, pending changes and when they take effect, exclusion state, options) |
| PUT | `/limits` | `{daily, weekly, monthly}`: decimal string or `null` |
| POST | `/exclude` | `{duration, confirm: true}` |
| PUT | `/reality-check` | `{minutes}` or `null` |

Every change is audited (`rg.limits_changed`, `rg.self_excluded`).

## Not implemented (operator / jurisdiction dependent)
* Loss, wager and session-time limits.
* Integration with national self-exclusion registers.
* Age and identity verification (KYC).
* Affordability checks.
* Marketing suppression for excluded players: CRM integrations must read `responsible_gaming_settings`.
* Admin-side lifting of an exclusion after it expires, where a regulator requires a manual review.
* Legacy third-party provider games launched by URL outside `GameController::show` must be checked one by one once their adapters exist (Phase 7, blocked).

The player-facing text says "24 hours". If you change the delay, update the string `Lower limits apply immediately…` in `lang/*.json`.
