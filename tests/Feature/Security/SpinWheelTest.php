<?php

namespace Tests\Feature\Security;

use App\Models\SpinConfigs;
use App\Models\Wallet;

class SpinWheelTest extends SecurityTestCase
{
    public function test_spin_token_can_be_redeemed_only_once(): void
    {
        SpinConfigs::create(['prizes' => json_encode([['value' => 10], ['value' => 10]])]);
        \App\Models\Setting::query()->update(['disable_spin' => 1]);
        \Cache::flush();

        $spin  = $this->postJson('/api/spin/result')->assertOk()->json();
        $token = base64_encode(json_encode(['signature' => $spin['signature'], 'nonce' => $spin['nonce']]));

        foreach (['a@example.test', 'b@example.test'] as $email) {
            $this->postJson('/api/auth/register', [
                'name' => 'P', 'email' => $email, 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
                'phone' => '+15550100', 'term_a' => 1, 'agreement' => 1, 'spin_token' => $token,
            ])->assertOk();
        }

        $bonuses = Wallet::query()->pluck('balance_bonus')->map(fn ($v) => (float) $v)->sort()->values()->all();
        $this->assertSame([0.0, 10.0], $bonuses);
    }
}
