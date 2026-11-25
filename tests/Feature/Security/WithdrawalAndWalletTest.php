<?php

namespace Tests\Feature\Security;

use App\Core\Payments\WithdrawalService;
use App\Models\Wallet;
use App\Models\Withdrawal;

class WithdrawalAndWalletTest extends SecurityTestCase
{
    public function test_request_cannot_overdraw(): void
    {
        $user = $this->makeUser();
        $this->wallet($user)->update(['balance_withdrawal' => 30]);

        $this->assertNotNull(WithdrawalService::request('user', $user->id, '20.00', ['type' => 'pix']));
        $this->assertNull(WithdrawalService::request('user', $user->id, '20.00', ['type' => 'pix']));
        $this->assertNull(WithdrawalService::request('user', $user->id, '-5', ['type' => 'pix']));
        $this->assertEquals(10.00, (float) $this->wallet($user)->balance_withdrawal);
    }

    public function test_cancel_refunds_only_once(): void
    {
        $user = $this->makeUser();
        $this->wallet($user)->update(['balance_withdrawal' => 30, 'currency' => 'EUR']);
        $w = WithdrawalService::request('user', $user->id, '30.00', ['type' => 'pix', 'currency' => 'EUR']);

        $this->assertTrue(WithdrawalService::cancel('user', $w->id));
        $this->assertFalse(WithdrawalService::cancel('user', $w->id));
        $this->assertEquals(30.00, (float) $this->wallet($user)->balance_withdrawal);
    }

    public function test_approve_pays_out_only_once_and_failed_payout_returns_to_pending(): void
    {
        $user = $this->makeUser();
        $this->wallet($user)->update(['balance_withdrawal' => 30]);
        $w = WithdrawalService::request('user', $user->id, '30.00', ['type' => 'pix']);

        $this->assertFalse(WithdrawalService::approve('user', $w->id, fn () => false));
        $this->assertSame(WithdrawalService::PENDING, (int) $w->fresh()->status);

        $calls = 0;
        $payout = function () use (&$calls) { $calls++; return true; };
        $this->assertTrue(WithdrawalService::approve('user', $w->id, $payout));
        $this->assertFalse(WithdrawalService::approve('user', $w->id, $payout));
        $this->assertSame(1, $calls);
        $this->assertFalse(WithdrawalService::cancel('user', $w->id)); // paid withdrawals cannot be refunded
        $this->assertSame(WithdrawalService::PAID, (int) $w->fresh()->status);
    }

    public function test_player_cannot_activate_another_players_wallet(): void
    {
        $me    = $this->makeUser();
        $other = $this->makeUser();
        $otherWallet = $this->wallet($other);
        $otherWallet->update(['active' => 0]);

        $this->actingAs($me, 'api')->postJson('/api/profile/mywallet/'.$otherWallet->id)->assertNotFound();
        $this->assertSame(0, (int) $otherWallet->fresh()->active);
    }

    public function test_withdrawal_api_is_atomic(): void
    {
        $user = $this->makeUser();
        $this->wallet($user)->update(['balance_withdrawal' => 25]);

        $body = ['type' => 'pix', 'amount' => '20', 'pix_type' => 'email', 'pix_key' => 'a@b.test', 'accept_terms' => true, 'currency' => 'EUR', 'symbol' => '€'];
        $this->actingAs($user, 'api')->postJson('/api/wallet/withdraw/request', $body)->assertOk();
        $this->actingAs($user, 'api')->postJson('/api/wallet/withdraw/request', $body)->assertStatus(400);

        $this->assertSame(1, Withdrawal::where('user_id', $user->id)->count());
        $this->assertEquals(5.00, (float) Wallet::where('user_id', $user->id)->value('balance_withdrawal'));
    }
}
