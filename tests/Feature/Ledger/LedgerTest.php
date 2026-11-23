<?php

namespace Tests\Feature\Ledger;

use App\Core\Ledger\LedgerService;
use App\Core\Ledger\Models\LedgerEntry;
use App\Core\Ledger\Models\LedgerJournal;
use App\Core\Money\Money;
use App\Core\Payments\DepositSettlementService;
use App\Core\Payments\WithdrawalService;
use App\Core\Wallet\LedgerContext;
use App\Core\Wallet\WalletService;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\SecurityTestCase;

class LedgerTest extends SecurityTestCase
{
    private function reconcileOk(): void
    {
        $this->assertSame(0, \Artisan::call('wallet:reconcile'), \Artisan::output());
    }

    private function setRaw(Wallet $wallet, array $values): void
    {
        DB::table('wallets')->where('id', $wallet->id)->update($values); // simulate pre-ledger data
    }

    public function test_credit_and_debit_post_balanced_journals_that_reconcile(): void
    {
        $wallet = $this->wallet($this->makeUser());

        $this->assertTrue(WalletService::credit($wallet, 'balance', '100.00', 'adjustment', 'adjustments')->ok);
        $this->assertTrue(WalletService::debit($wallet, 'balance', '30.25', 'adjustment', 'adjustments')->ok);

        $this->assertEquals(69.75, (float) $wallet->fresh()->balance);
        foreach (LedgerJournal::with('entries')->get() as $journal) {
            $this->assertSame(0, $journal->entries->sum('amount_minor'));
        }
        $this->assertSame(6975, LedgerEntry::whereHas('account', fn ($q) => $q->where('code', "wallet:{$wallet->id}:balance"))->latest('id')->value('balance_after_minor'));
        $this->reconcileOk();
    }

    public function test_idempotency_key_applies_once(): void
    {
        $wallet = $this->wallet($this->makeUser());

        $first  = WalletService::credit($wallet, 'balance', '10.00', 'deposit', 'deposits', 'deposit:abc');
        $second = WalletService::credit($wallet, 'balance', '10.00', 'deposit', 'deposits', 'deposit:abc');

        $this->assertFalse($first->duplicate);
        $this->assertTrue($second->duplicate);
        $this->assertEquals(10.00, (float) $wallet->fresh()->balance);
        $this->assertSame(1, LedgerJournal::where('idempotency_key', 'deposit:abc')->count());
    }

    public function test_debit_cannot_overdraw_and_writes_nothing(): void
    {
        $wallet = $this->wallet($this->makeUser());
        WalletService::credit($wallet, 'balance', '5.00', 'adjustment', 'adjustments');
        $journals = LedgerJournal::count();

        $result = WalletService::debit($wallet, 'balance', '5.01', 'adjustment', 'adjustments');

        $this->assertFalse($result->ok);
        $this->assertSame('insufficient_funds', $result->reason);
        $this->assertSame($journals, LedgerJournal::count());
        $this->assertEquals(5.00, (float) $wallet->fresh()->balance);
    }

    public function test_pre_ledger_balances_are_opened_on_first_touch(): void
    {
        $wallet = $this->wallet($this->makeUser());
        $this->setRaw($wallet, ['balance' => '42.00', 'balance_bonus' => '7.00']);

        WalletService::credit($wallet->fresh(), 'balance', '8.00', 'adjustment', 'adjustments');

        $this->assertSame(1, LedgerJournal::where('type', 'opening_balance')->count());
        $this->assertSame(0, \Artisan::call('wallet:reconcile', ['--open' => true])); // opens balance_bonus too
        $this->assertSame(2, LedgerJournal::where('type', 'opening_balance')->count());
        $this->reconcileOk();
    }

    public function test_ledger_rows_are_immutable(): void
    {
        $wallet = $this->wallet($this->makeUser());
        WalletService::credit($wallet, 'balance', '1.00', 'adjustment', 'adjustments');

        $this->expectException(\LogicException::class);
        LedgerEntry::first()->update(['amount_minor' => 999999]);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $wallet = $this->wallet($this->makeUser());

        $this->expectException(\InvalidArgumentException::class);
        WalletService::credit($wallet, 'balance', Money::of('1.00', 'USD'), 'adjustment', 'adjustments');
    }

    public function test_legacy_model_writes_are_journaled_with_exact_db_delta(): void
    {
        $wallet = $this->wallet($this->makeUser());
        $stale  = $wallet->fresh();
        WalletService::credit($wallet, 'balance', '50.00', 'adjustment', 'adjustments');

        // Stale in-memory instance still thinks balance = 0; the delta must come from the DB (50 -> 5 = -45),
        // not from the in-memory original (0 -> 5 = +5).
        LedgerContext::run('game.settlement', 'game', fn () => $stale->update(['balance' => 5]), ['reference_id' => 'tx-9']);
        $wallet->fresh()->increment('balance_withdrawal', 12.5);

        $observed = LedgerJournal::where('source', 'observed')->orderBy('id')->get();
        $this->assertSame(['game.settlement', 'unattributed'], $observed->pluck('type')->all());
        $this->assertSame('tx-9', $observed[0]->reference_id);
        $this->assertSame(-4500, $observed[0]->entries()->whereNotNull('balance_after_minor')->value('amount_minor'));
        $this->reconcileOk();
    }

    public function test_bet_debit_keeps_legacy_bucket_priority(): void
    {
        $cases = [
            // balance, withdrawal, bonus, bet => expected bucket, balances after
            [['10.00', '0.00', '0.00'], '4.00', 'balance', ['6.00', '0.00', '0.00']],
            [['1.00', '10.00', '0.00'], '4.00', 'balance_withdrawal', ['1.00', '6.00', '0.00']],
            [['3.00', '2.00', '0.00'], '4.00', 'balance_withdrawal', ['0.00', '1.00', '0.00']],
            [['0.00', '0.00', '9.00'], '4.00', 'balance_bonus', ['0.00', '0.00', '5.00']],
            [['1.00', '0.00', '9.00'], '4.00', 'no_balance', ['1.00', '0.00', '9.00']],
        ];

        foreach ($cases as [$start, $bet, $expected, $after]) {
            $wallet = $this->wallet($this->makeUser());
            $this->setRaw($wallet, ['balance' => $start[0], 'balance_withdrawal' => $start[1], 'balance_bonus' => $start[2]]);
            $wallet = $wallet->fresh();

            $this->assertSame($expected, \Helper::DiscountBalance($wallet, $bet), json_encode($start));
            $fresh = $wallet->fresh();
            $actual = array_map(fn ($v) => \App\Core\Money\Amount::normalize((string) $v), [$fresh->balance, $fresh->balance_withdrawal, $fresh->balance_bonus]);
            $this->assertSame($after, $actual, json_encode($start));
        }

        // Untouched pre-ledger balances (the no_balance case) are flagged until opened.
        $this->assertSame(1, \Artisan::call('wallet:reconcile'));
        \Artisan::call('wallet:reconcile', ['--open' => true]);
        $this->reconcileOk();
    }

    public function test_negative_bet_is_not_a_credit(): void
    {
        $wallet = $this->wallet($this->makeUser());
        $this->assertSame('no_balance', \Helper::DiscountBalance($wallet, -100));
        $this->assertEquals(0, (float) $wallet->fresh()->balance);
    }

    public function test_deposit_and_withdrawal_lifecycle_is_fully_journaled(): void
    {
        $user = $this->makeUser();
        $this->pendingDeposit($user, 'tx-l1', '80.00');
        $this->assertTrue(DepositSettlementService::settle('tx-l1', '80.00'));

        $w = WithdrawalService::request('user', $user->id, '30.00', ['type' => 'pix', 'currency' => 'EUR']);
        $this->assertTrue(WithdrawalService::cancel('user', $w->id));
        WithdrawalService::request('user', $user->id, '20.00', ['type' => 'pix', 'currency' => 'EUR']);

        $this->assertSame(
            ['deposit', 'withdrawal.request', 'withdrawal.cancel', 'withdrawal.request'],
            LedgerJournal::orderBy('id')->pluck('type')->all()
        );
        $this->assertEquals(60.00, (float) $this->wallet($user)->balance_withdrawal);
        $this->assertSame(-8000, LedgerService::accountBalanceMinor(LedgerService::platformAccount('deposits', 'EUR')));
        $this->assertSame(2000, LedgerService::accountBalanceMinor(LedgerService::platformAccount('withdrawals', 'EUR')));
        $this->assertSame(0, LedgerJournal::where('source', 'observed')->count());
        $this->reconcileOk();
    }
}
