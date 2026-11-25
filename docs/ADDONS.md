# Addon Framework

_Phase 3. Code: `app/Core/Addons`. Admin UI: **Admin → Modulos → Addons**._

Integrations are packages that the core loads without being modified. Payment gateways are the first ones: `addons/payment-{suitpay,digitopay,mercadopago,sharkpay}`.

## Trust model

* **No code arrives through the admin panel.** Addons are discovered only in the directories listed in `config/addons.php` (`paths`), which are populated by your release pipeline (git, composer, artifact). The former ZIP "updater" that extracted uploads over the app is gone.
* **Integrity:** `addon.lock` lists the SHA-256 of every file (symlinks are rejected).
  * Install, enable, update and `addon:package verify` re-hash everything. A missing, extra or modified file blocks the action.
  * On every boot, an enabled addon is loaded only if its `addon.lock` still hashes to the value recorded at enable time.
* **Authenticity:** `addon.sig` is an Ed25519 signature over `addon.lock`, from a key in `ADDONS_TRUSTED_KEYS`.
  * `third_party` paths: a signature is **always** required.
  * `bundled` paths (shipped in this repository, the same review boundary as the core): a signature is optional unless `ADDONS_REQUIRE_SIGNATURE_BUNDLED=true`. Turn that on once your CI signs releases.
* **Compatibility:** `requires.platform` is checked against `config('addons.platform_version')`, and `requires.addons` against installed and enabled versions.
* **Data safety:** uninstall keeps the addon's tables and data unless `--purge` (which asks for confirmation). Every state change is written to `audit_logs`.
* **Secrets:** addon configuration is stored encrypted with `APP_KEY`, masked in the UI, never sent back to the browser, and never logged (only the changed key names are).

## Manifest (`addon.json`)

```json
{
  "id": "payment.stripe",
  "name": "Stripe Payments",
  "version": "1.0.0",
  "description": "Card payments via Stripe Checkout.",
  "author": "Example Ltd",
  "category": "payments",
  "requires": { "platform": "^1.0", "addons": {} },
  "autoload": { "psr-4": { "Addons\\Payments\\Stripe\\": "src/" } },
  "providers": ["Addons\\Payments\\Stripe\\StripeServiceProvider"],
  "contributes": { "payment_gateways": { "stripe": "Addons\\Payments\\Stripe\\StripeGateway" } },
  "permissions": ["payments.stripe.configure"],
  "config": [
    { "key": "secret_key", "label": "Secret key", "type": "string", "secret": true, "required": true }
  ],
  "migrations": "database/migrations",
  "docs": "README.md"
}
```

Validation (`AddonManifest::fromArray`):
* lowercase dotted id;
* semver version;
* a known category;
* class names well-formed;
* autoload, migration and docs paths relative with no `..`;
* config keys unique, of type `string|text|url|integer|boolean|select` (`url` must be `https://`).

### Contribution points
| Key | Contract | Consumer |
|---|---|---|
| `contributes.payment_gateways` | `App\Core\Payments\Contracts\PaymentGatewayInterface` (optionally `SupportsManualPayout`) | `App\Core\Payments\PaymentGateways` |
| `providers` | any Laravel `ServiceProvider` (routes, listeners, Filament pages) | Laravel |

Game-provider contributions (`contributes.game_providers`) arrive with the Phase 7 adapters.

## Lifecycle

```bash
php artisan addon:list                          # discovered addons, state, verification problems
php artisan addon install payment.suitpay --enable
php artisan addon enable|disable|update payment.suitpay
php artisan addon uninstall payment.suitpay [--purge]
php artisan addon:sync --enable                 # deploy step: install/update all bundled addons
```

Release pipeline (never on production with the secret key):

```bash
php artisan addon:package keygen                # once; keep the secret in CI secrets
php artisan addon:package lock --all            # after changing addon files
ADDON_SIGNING_KEY=... php artisan addon:package sign payment.suitpay --key-id=release-2026
php artisan addon:package verify                # also scheduled hourly
```

**Rollback:** deploy the previous release (its files and `addon.lock`), then run `php artisan addon update <id>`. Down-migrations are the addon author's responsibility and must be documented in the addon README.

## Writing a payment gateway addon
1. Create `addons/payment-acme/` with `addon.json` (as above) and `src/AcmeGateway.php` extending `App\Core\Payments\Gateways\AbstractPaymentGateway`.
2. Implement:
   * `verifyDeposit()` against the provider's **real** status API. Report amount and currency when the API returns them, so settlement can check them.
   * `callbackPaymentId()`, which extracts the ID from the webhook. The body is only a hint.
   * `createDeposit()` and `createWithdrawal()`.
   * `validateConfiguration()`.
3. Throw `UnsupportedCapability` for anything you don't implement. Never return fake success.
4. Read credentials with `GatewayCredentials::get('payment.acme', 'key')`, and send them only to hosts allowed through `TrustedEndpoint`.
5. Document endpoints, the status vocabulary and sandbox test steps in `README.md`, then run `addon:package lock`.

Deposits are credited only by `DepositSettlementService::settle()`, which is locked, idempotent and amount-checked.

## Deployment note (breaking change)
Payment gateways are no longer part of the core. After deploying this release run:

```bash
php artisan migrate
php artisan addon:sync --enable
```

Until then `PaymentGateways` is empty and deposits and payouts are unavailable. Existing credentials keep working: each addon falls back to the legacy `gateways` table columns until they are re-entered in the addon manager. Re-enter them there to have them encrypted at rest.
