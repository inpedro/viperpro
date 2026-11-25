# Ledger and Wallet Engine

_Phase 2. Code: `app/Core/Ledger`, `app/Core/Wallet`, `app/Core/Money`._

## Model

* **Accounts** (`ledger_accounts`):
  * one per wallet bucket: `wallet:{walletId}:{bucket}`, where the bucket is `balance`, `balance_bonus`, `balance_withdrawal` or `refer_rewards`;
  * one per platform purpose and currency: `platform:{name}:{CUR}`.
* **Journals** (`ledger_journals`): one business event, with type, idempotency key (unique), reference, actor, `source` (`service` or `observed`) and metadata.
* **Entries** (`ledger_entries`):
  * signed integer minor units; the entries of a journal sum to zero per currency (enforced before writing);
  * player-account entries carry `balance_after_minor`.
* Journals and entries are **immutable**: model-level updates and deletes throw. Corrections are reversal journals.
* `wallets.*` balance columns are a **projection**. Invariant: projection = sum of that account's entries.

Platform accounts in use:

| Account | Meaning |
|---|---|
| `platform:deposits` | money received through gateways (negative = owed to players) |
| `platform:withdrawals` | withdrawals requested by players (debited from their bucket) |
| `platform:game` | game settlement (house side of bets and wins) |
| `platform:bonus` | bonus expense (first deposit, spin) |
| `platform:affiliate` | affiliate CPA and revenue share |
| `platform:adjustments` | manual corrections through `WalletService` |
| `platform:opening` | balances that existed before the ledger |
| `platform:unattributed` | changes made by legacy code without a `LedgerContext` (should trend to zero) |

## Writing money

Always use `App\Core\Wallet\WalletService`:

```php
WalletService::credit($wallet, 'balance', '10.00', 'deposit', 'deposits', "deposit:{$paymentId}", ['reference_type' => 'transaction', 'reference_id' => $id]);
WalletService::debit($wallet, 'balance_withdrawal', $amount, 'withdrawal.request', 'withdrawals', "withdrawal:request:user:{$id}");
WalletService::transfer($wallet, 'balance', 'balance_withdrawal', $amount, 'rollover.complete');
WalletService::debitBet($wallet, $bet, "bet:{$provider}:{$txnId}");
```

Each call:
1. locks the wallet row;
2. returns `duplicate` if the idempotency key was already used;
3. refuses to overdraw;
4. updates the projection with a query-builder `UPDATE`;
5. posts the balanced journal, all in one DB transaction.

Amounts are decimal strings or `Money`; floats are only tolerated at legacy boundaries.

## Legacy safety net

`WalletLedgerObserver` journals every balance change that still goes through the `Wallet` Eloquent model (`$wallet->increment()`, `$wallet->update()`, Filament edits).
* The delta is read from the **database** before and after the write, not from possibly stale in-memory attributes.
* These journals have `source = observed`. Their type and counter account come from `LedgerContext::run()` when the caller set one (game settlement, affiliate revenue share), otherwise `unattributed`.
* The journal records the `call_site` file and line in `meta`, to drive the migration.

Call sites still covered only by the observer (to migrate to `WalletService` with provider-transaction idempotency keys):

| Location | What | Context today |
|---|---|---|
| `Helper::payWithRollover` | win credits, rollover conversions | `game.settlement` |
| `Helper::PayLoss` / `PayWin` | affiliate revenue share / clawback | `affiliate.revshare(_clawback)` |
| `Traits/Providers/VibraTrait`, `SalsaGamesTrait`, `PlayIGamingTrait` (refund) | provider-specific credits | unattributed |
| `Traits/Affiliates/EarningTrait`, `Traits/Missions/MissionTrait` | rewards | unattributed |
| Filament `WalletResource` edits | manual admin changes (actor recorded) | unattributed |

The rollover rules in `payWithRollover` are not rewritten yet. They contain latent bugs (for example, `=== 0` comparisons against decimal strings never match), and changing financial rules needs product sign-off plus characterisation tests first.

`SystemWalletController` maintains a separate house "system wallet" row (not a player wallet) and is outside the ledger for now.

## Operations

```bash
php artisan migrate                     # ledger tables
php artisan wallet:reconcile --open     # once after deploy: opening journals for existing balances
php artisan wallet:reconcile            # schedule hourly; exit 1 on any mismatch or unbalanced journal
```

`ensureOpened()` also opens a bucket automatically the first time it is touched, so the invariant holds even before the `--open` run.

**Dashboard.** Every run is stored per casino (`reconciliation_runs`):
* Admin → Reconciliation shows the latest result, the first 200 mismatches (wallet, balance, wallet vs ledger, difference) and the history. **Run now** is rate-limited to once a minute and audited.
* Platform → Reconciliation shows every casino's latest result.
* A failed run logs `wallet.reconciliation_failed`.
* Runs are read-only: nothing is corrected automatically, and the ledger remains the source of truth.

Migration `2026_12_01_000001_create_reconciliation_runs_table` is reversible.

## Limits (documented, not hidden)

* **Precision is 2 decimals**, because every wallet column is `decimal(20,2)`. Currencies with other precisions (JPY 0, USDT 6) need those columns widened first, plus per-currency precision in `Money`.
* There is **no FX.** Mixing currencies throws. One wallet has one currency.
* Bets debited through `Helper::DiscountBalance` have **no idempotency key** yet, because the legacy providers do not pass their transaction ID down. Provider-level duplicate checks (`orders.transaction_id`) remain the only protection until Phase 7.
* Immutability is enforced in the application. Revoking `UPDATE`/`DELETE` on ledger tables from the app's DB user is a deployment task (Phase 9).
* Tests run on SQLite. Row locking (`FOR UPDATE`) is verified only on MySQL/InnoDB staging.
