# Stripe Payments (`payment.stripe`)

Deposits through **Stripe Checkout** (hosted payment page), built on the official `stripe/stripe-php` SDK and Stripe's documented Checkout and webhook flow.

| | |
|---|---|
| Deposit | `POST /api/wallet/deposit/payment` with `gateway=stripe`, `amount` → `{checkout_url}`; the player is redirected to Stripe |
| Webhook endpoint | `POST https://<casino-domain>/payments/webhooks/stripe` (one endpoint per casino domain) |
| Events | `checkout.session.completed`, `checkout.session.async_payment_succeeded` |
| Verification | `Stripe-Signature` (HMAC-SHA256 + timestamp tolerance) **and** re-fetch of the Checkout Session; settles `amount_total`/`currency` from the API |
| Idempotency | Checkout created with an idempotency key; settlement keyed by session id (`deposit:cs_...`) |
| Refunds | supported (Refunds API on the session's PaymentIntent) with a ledger reversal; refused when the player has already spent the funds |
| Payouts | not supported (pay-in only) |
| Currencies | 2-decimal currencies only (EUR, USD, GBP, ...), matching the wallet engine's precision |

## Configuration (Admin → Addons → Stripe → Configure)
* **Secret key:** use a restricted key (`rk_...`) with Checkout Sessions write, PaymentIntents read and Refunds write permissions where possible.
* **Webhook signing secret:** in the Stripe Dashboard, under Developers → Webhooks, add the endpoint above for the two events, then copy its `whsec_...`.
* The legacy `gateways.stripe_secret_key` column and `STRIPE_WEBHOOK_SECRET` are used only as fallbacks.

## Before production
1. Run a test-mode deposit end to end (test card `4242 4242 4242 4242`) and confirm the wallet is credited once.
2. Replay the webhook from the Dashboard and confirm it is acknowledged without crediting again.
3. Run a refund and confirm the ledger shows `deposit.refund`.
4. Card payments for gambling are restricted by Stripe and by card schemes in many markets. **Confirm with Stripe that your account is approved for your licence and jurisdictions.**
