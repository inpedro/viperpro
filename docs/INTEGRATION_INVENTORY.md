# Integration Inventory — Viper Pro

_Phase 0 deliverable, updated with Phase 1 status._ "Mounted" means the routes are loaded by `routes/web.php` / `routes/api.php`.

None of these integrations has been tested against a live provider in this work. Every claim below comes from reading the code. Hosts are the ones already present in the code. **Each must be confirmed against the provider's official documentation before use**, and none was invented here.

## 1. Payment gateways

| Gateway | Code | Outbound API host (from code) | Deposit confirmation before Phase 1 | Phase 1 status | Payouts |
|---|---|---|---|---|---|
| **SuitPay** | `Traits/Gateways/SuitpayTrait`, `Gateway/SuitPayController` | `ws.suitpay.app` (DB default) | Trusted the callback body (`statusTransaction`) | Callback is only a hint. Status fetched from `POST gateway/consult-status-transaction` with `ci`/`cs` headers, then atomic settlement. Host allowlisted. | PIX cash-out via `pixCashOut` |
| **DigitoPay** | `DigitoPayTrait`, `DigitoPayController` | **Default was `si5n56mrnjzvt5gr2f536ildr40sqzke.lambda-url.sa-east-1.on.aws`** (anonymous AWS Lambda) | Trusted the callback body | Lambda default removed from the code, the admin form, the SQL dump and existing DB rows (migration). Credentials are sent only to hosts in `DIGITOPAY_ALLOWED_HOSTS` (**empty by default, so DigitoPay is disabled**). Status re-checked via `GET api/status/{id}`. | `api/withdraw` |
| **Mercado Pago** | `MercadoPagoTrait`, `MercadoPagoController` | `api.mercadopago.com` | Callback expected SuitPay-shaped JSON (it wouldn't match MP's real notifications) and trusted it | Accepts MP's `{"type":"payment","data":{"id"}}` shape. Verifies with `GET /v1/payments/{id}`: status `approved` **and amount and currency match**. | `pixCashOutMP` (unverified, see below) |
| **SharkPay** | `SharkPayTrait`, `SharkPayController` | `sharkpay.com.br` (hardcoded) | Marked the transaction paid **before** finalizing, so the wallet was never credited, and trusted `reference` from the body | Status from `GET pix/txid/{id}` (`invoice.status == 1`), then atomic settlement | `pixCashOutSharkPay` |
| **Stripe** | `addons/payment-stripe` (Phase 7) | Stripe API (official SDK) | n/a (rebuilt) | **Rebuilt as an addon:** Checkout Sessions in the wallet currency, `Stripe-Signature` verification (per-tenant `whsec_`) + session re-fetch + tenant binding, idempotent settlement, refunds with ledger reversal. The old unmounted controller/trait/service were removed; the frontend no longer receives any key. Health `ok` when configured. | none (pay-in only) |
| **BsPay** | `BsPayTrait`, `BsPayController` | none configured | Credited on an unauthenticated callback | **Not mounted** (the route file isn't included). Callback and payout now return 410 if mounted. Needs a new adapter. | disabled |

**Payout methods** are declared by each gateway (`getPayoutMethods()`: PIX gateways → `pix`). The withdrawal API accepts those plus `bank` (paid manually by the operator), and gateways refuse payouts of other methods.

Status vocabularies observed in code (to confirm with providers): SuitPay `PAID_OUT`/`PAYMENT_ACCEPT`; DigitoPay `REALIZADO`; MP `approved`; SharkPay `invoice.status == 1`; Stripe `checkout.session.completed` + `payment_status == paid`.

**Open questions for each provider (needed for Phase 7 adapters):** a webhook signature scheme (none of the PIX gateways' code uses one); payout callback semantics (`*/payment` routes are now log-only); refund API; sandbox credentials.

## 2. Game providers / aggregators

| Provider | Code | Callback route (mounted?) | Auth on callback before Phase 1 | Phase 1 status |
|---|---|---|---|---|
| **Fivers** (`gold_api`) | `FiversTrait` | `POST gold_api`, `gold_api/*` (yes) | none; `user_code` = raw user id | Behind `provider.callback:fivers`. Fail closed until `FIVERS_CALLBACK_IPS` and/or secret are configured. |
| **VeniX** | `VeniXTrait` | `POST venix_api` (yes) | none | `provider.callback:venix` |
| **PlayIGaming** | `PlayIGamingTrait` (`playigaming.fun`) | `POST playigaming_api` (yes) | none | `provider.callback:playigaming` |
| **Evergame** | `EvergameTrait` (`api.evergame.org`) | `POST ever` (yes) | none | `provider.callback:evergame` |
| **PlayGaming** | `PlayGamingTrait` (`tbs2api.aslot.net`) | `POST playgaming` (yes) | none (only signs outbound requests) | `provider.callback:playgaming` |
| **Salsa** | `SalsaGamesTrait` | `POST cron/salsa/webhook` (yes) | token decoded with plain base64 | `provider.callback:salsa`; session tokens now signed |
| **KaGaming** | `KaGamingTrait` (`rmpstage.kaga88.com` = staging host) | `kagaming/webhooks` (**not mounted**) | HMAC with an **empty** hardcoded key | Route guarded if mounted. Hardcoded empty key must become config. |
| **Vibra** | `VibraTrait` (`assets.mick.vsslots.com`) | `vibra/webhooks` (**not mounted**) | session token (forgeable) | Route guarded; tokens now signed |
| **Slotegrator** | `SlotegratorTrait` | not mounted | X-Sign HMAC (sha1) implemented | Untouched; error leakage fixed; player id now a stable signed token |
| **WorldSlot**, **Games2Api** | traits | launch only | — | Untouched |
| **In-house "originals"** | `Controllers/Games/*`, `PrivateGamesTrait`, `public/originals/*` | `api/vgames/{token}/{action}` | forgeable token | Signed tokens. Demo-agent rigging removed. **Not a certified RNG; clones of commercial titles (see security audit §5).** |

**Provider-side idempotency:** Fivers and VeniX check `orders.transaction_id` for some actions but not consistently, and there is no rollback/refund verification. Mitigated in Phase 1 by authenticating callers. A full fix needs each provider's protocol documentation (Phase 7).

## 3. Other external services

| Service | Use | Status |
|---|---|---|
| Google Fonts API | `Helper::getGoogleFonts` | **API key was hardcoded** (`AIza…3yQ`): revoke it. Now `GOOGLE_FONTS_API_KEY`. |
| Pusher | broadcasting | env configured |
| Socialite | social login (`routes/groups/auth/social.php`) | unaudited (Phase 2) |
| Mail | password reset | template is Portuguese only (Phase 5) |
