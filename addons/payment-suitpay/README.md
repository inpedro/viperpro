# SuitPay Payments (`payment.suitpay`)

SuitPay PIX deposits and payouts (Brazil).

**Status:** legacy integration refactored behind `PaymentGatewayInterface`. It has **not** been verified
against the provider's official documentation or sandbox (Phase 7). Host and endpoints below are the
ones the original code used.

| | |
|---|---|
| Endpoints used | POST {api_url}gateway/request-qrcode, POST gateway/consult-status-transaction, PIX cash-out |
| Allowed API host (`SUITPAY_ALLOWED_HOSTS`) | ws.suitpay.app |
| "Paid" status | `PAID_OUT / PAYMENT_ACCEPT` |
| Server-side verification | status only (no amount) |
| Callback route | `POST /suitpay/callback` (body is a hint only; status is re-fetched) |
| Capabilities | deposit, deposit verification, withdrawal |
| Not supported | refunds, payout verification, signed webhooks |

## Configuration
Configure in **Admin → Addons** (values are stored encrypted with `APP_KEY`; secrets are masked).
Until configured there, the legacy plaintext columns in the `gateways` table are used as a fallback.

## Testing requirements before production
1. Obtain sandbox credentials and the provider's current API documentation.
2. Confirm the API host and add it to `SUITPAY_ALLOWED_HOSTS`.
3. Confirm the status vocabulary and whether webhooks can be signed; if so, implement signature checks.
4. Run a sandbox deposit, a duplicate/replayed callback, an amount-mismatch case and a payout.
