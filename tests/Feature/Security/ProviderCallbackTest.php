<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;

class ProviderCallbackTest extends SecurityTestCase
{
    public function test_unconfigured_provider_callbacks_fail_closed(): void
    {
        $user = $this->makeUser();

        foreach (['/gold_api', '/venix_api', '/playigaming_api', '/ever', '/playgaming'] as $uri) {
            $this->postJson($uri, [
                'method' => 'transaction', 'user_code' => $user->id, 'user_id' => $user->id,
                'game_type' => 'slot', 'slot' => ['txn_id' => 'x', 'bet_money' => 0, 'win_money' => 100000],
            ])->assertStatus(503);
        }

        $this->assertEquals(0, (float) $this->wallet($user)->balance);
        $this->assertSame(5, DB::table('callback_logs')->where('channel', 'provider')->where('outcome', 'rejected')->count());
    }

    public function test_wrong_secret_and_wrong_ip_are_rejected(): void
    {
        config([
            'game_providers.callbacks.fivers.shared_secret' => 'expected-secret',
            'game_providers.callbacks.fivers.secret_source' => 'body:agent_secret',
        ]);
        $this->postJson('/gold_api', ['method' => 'user_balance', 'agent_secret' => 'wrong'])->assertStatus(403);
        $this->postJson('/gold_api', ['method' => 'user_balance'])->assertStatus(403);

        config([
            'game_providers.callbacks.fivers.shared_secret' => '',
            'game_providers.callbacks.fivers.allowed_ips'   => ['203.0.113.10'],
        ]);
        $this->postJson('/gold_api', ['method' => 'user_balance'])->assertStatus(403);
    }

    public function test_correct_secret_reaches_the_handler(): void
    {
        config([
            'game_providers.callbacks.fivers.shared_secret' => 'expected-secret',
            'game_providers.callbacks.fivers.secret_source' => 'body:agent_secret',
        ]);

        $this->postJson('/gold_api', ['method' => 'unknown', 'agent_secret' => 'expected-secret'])
            ->assertOk()->assertJson(['status' => 0]);
    }
}
