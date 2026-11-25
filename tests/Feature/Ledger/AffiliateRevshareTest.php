<?php

namespace Tests\Feature\Ledger;

use App\Helpers\Core;
use App\Models\AffiliateHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Affiliates used to be shown a "fake" revenue-share rate, which was also applied when their
 * players won (deducting more than they ever earned). Only the real rate may be used.
 */
class AffiliateRevshareTest extends SecurityTestCase
{
    public function test_player_wins_are_deducted_at_the_real_rate_not_the_displayed_fake_one(): void
    {
        // The "fake" column exists on installations built from the original SQL dump.
        if (! Schema::hasColumn('users', 'affiliate_revenue_share_fake')) {
            Schema::table('users', fn ($t) => $t->integer('affiliate_revenue_share_fake')->nullable());
        }
        DB::table('settings')->update(['revshare_reverse' => 1]);
        Cache::flush();

        $sponsor = $this->makeUser();
        $sponsor->forceFill(['affiliate_revenue_share' => 20, 'affiliate_revenue_share_fake' => 50])->save();
        DB::table('wallets')->where('user_id', $sponsor->id)->update(['refer_rewards' => 100, 'active' => 1]);

        $player = $this->makeUser();
        $player->forceFill(['inviter' => $sponsor->id])->save();
        AffiliateHistory::create(['user_id' => $player->id, 'inviter' => $sponsor->id, 'commission' => 100, 'commission_type' => 'revshare',
            'commission_paid' => 100, 'deposited' => 0, 'deposited_amount' => 0, 'losses' => 0, 'losses_amount' => 0, 'status' => 0]);

        $payWin = new \ReflectionMethod(Core::class, 'PayWin');
        $payWin->invoke(null, $player->fresh(), $sponsor->fresh(), 'win', 10, 'balance');

        // 20% of 10 = 2.00 (the fake 50% would have taken 5.00)
        $this->assertEquals(98.0, (float) DB::table('wallets')->where('user_id', $sponsor->id)->value('refer_rewards'));
    }
}
