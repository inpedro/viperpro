# DigitoPay Payments (`payment.digitopay`)

DigitoPay PIX deposits and payouts (Brazil).

**Status:** legacy integration refactored behind `PaymentGatewayInterface`. It has **not** been verified
against the provider's official documentation or sandbox (Phase 7). Host and endpoints below are the
ones the original code used.

| | |
|---|---|
| Endpoints used | POST api/token/api, POST api/deposit, GET api/status/{id}, POST api/withdraw |
| Allowed API host (`DIGITOPAY_ALLOWED_HOSTS`) | (none: set from DigitoPay official docs) |
| "Paid" status | `REALIZADO` |
| Server-side verification | status only (no amount) |
| Callback route | `POST /digitopay/callback` (body is a hint only; status is re-fetched) |
| Capabilities | deposit, deposit verification, withdrawal |
| Not supported | refunds, payout verification, signed webhooks |

## Configuration
Configure in **Admin → Addons** (values are stored encrypted with `APP_KEY`; secrets are masked).
Until configured there, the legacy plaintext columns in the `gateways` table are used as a fallback.

## Testing requirements before production
1. Obtain sandbox credentials and the provider's current API documentation.
2. Confirm the API host and add it to `DIGITOPAY_ALLOWED_HOSTS`.
3. Confirm the status vocabulary and whether webhooks can be signed; if so, implement signature checks.
4. Run a sandbox deposit, a duplicate/replayed callback, an amount-mismatch case and a payout.
