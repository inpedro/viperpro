# Integrations: Building, Testing and Operating

_Phase 7. See also `ADDONS.md` (packaging), `INTEGRATION_INVENTORY.md` (status of each integration)._

## Payment gateways
Each gateway is an addon contributing a `PaymentGatewayInterface` adapter (usually extending `AbstractPaymentGateway`).

| Method | Rule |
|---|---|
| `createDeposit(Request)` | Create the payment at the provider and a pending `Transaction` + `Deposit` (status 0) whose `payment_id` is the provider's id. Never credit here. |
| `verifyDeposit(id)` | **Server-to-server** lookup at the provider. Return `PAID` only when the provider says so; include `amount`/`currency` when the API reports them. Unknown, unreachable or not configured → `UNKNOWN`. |
| `processWebhook(Request)` | Authenticate (a signature where the provider supports one), treat the body as a hint, then `verifyAndSettle($id)`. Return a `WebhookResult`: `credited`, `acknowledged` (authentic but nothing to do: the provider must not retry) or `rejected`. |
| `getPayoutMethods()` / `createWithdrawal()` | Payout methods offered (e.g. `pix`). Refuse withdrawals of any other method. |
| `refund()` | Post the ledger reversal first; compensate if the provider refuses. |
| anything not implemented | throw `UnsupportedCapability`; never return fake success |

Deposits are credited **only** by `DepositSettlementService::settle()`, which is locked, idempotent per payment id, and checks amount and currency.

Endpoints:
* `POST /payments/webhooks/{gateway}` (unified, per casino domain).
* The legacy `/{gateway}/callback` URLs still work.
* Every attempt is written to `callback_logs` with secrets redacted.

## Contract test harness
`tests/Feature/Integrations/GatewayContractTest.php` runs **every installed gateway**, including new addons, automatically, through:
* identity and capability consistency, including withdrawal capability ⇔ payout methods;
* disabled and fail-closed when unconfigured;
* unknown payments never verify as paid while the provider API is down;
* forged webhooks in every known shape never credit;
* unsupported operations throw;
* payouts of methods the gateway doesn't offer are refused.

Adapter-specific tests fake the provider API. Use `Http::fake()` for Laravel's HTTP client; for the Stripe SDK, `FakeStripeClient` plugged in through `ApiRequestor::setHttpClient()`.

`AddonBootTest` boots **separate PHP processes** against a file database. It exists because an in-process test could not catch the boot-order bug fixed in this phase.

## Operating
* **Admin → Modulos → Integrations:**
  * Health of each payment gateway active for the casino (status, capabilities, payout methods, configuration problems) and of the game providers.
  * 24-hour webhook counts by outcome.
  * A filterable, auto-refreshing webhook monitor with redacted payloads.
* `php artisan integrations:status [--all]` prints the same per tenant. Hourly, `--all` logs `integrations.unusable` warnings for gateways a casino activated but can't use.
* Stripe-style retries are acknowledged once settled (`acknowledged`), so providers don't retry forever. Amount and currency mismatches are acknowledged too, **never credited**, and logged for manual review.

## Game providers
The legacy provider traits (Fivers, VeniX, PlayIGaming, Evergame, PlayGaming, Salsa, KaGaming, Vibra) still handle their own protocols. They're reachable only through authenticated, fail-closed callbacks (Phase 1) and are described by `LegacyGameProvider`, whose health is `unverified`.

Rebuilding each one as a `GameProviderInterface` adapter (bets and wins through `WalletService` keyed by the provider transaction id, rollbacks, session validation) **requires the provider's official integration documentation and sandbox credentials, which are not available in this repository.** Inventing their APIs is explicitly out of bounds. The adapter contract, health reporting, callback authentication and this test harness are ready for when those materials are supplied.
