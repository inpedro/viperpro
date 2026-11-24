<?php

namespace Tests\Feature\Player;

use App\Core\Analytics\Analytics;
use App\Core\Content\LegalPage;
use App\Core\ResponsibleGaming\ResponsibleGaming;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use App\Core\Wallet\WalletService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\SecurityTestCase;

class ResponsibleGamingLegalAnalyticsTest extends SecurityTestCase
{
    private function api(User $user): self
    {
        auth('api')->forgetUser();

        return $this->withHeader('Authorization', 'Bearer '.auth('api')->login($user));
    }

    public function test_lowering_a_limit_is_immediate_and_raising_it_waits_for_the_cooling_off_period(): void
    {
        $user = $this->makeUser();

        $this->api($user)->putJson('/api/profile/responsible-gaming/limits', ['daily' => '100.00'])->assertOk();
        $this->assertSame('100.00', ResponsibleGaming::present(ResponsibleGaming::for($user))['deposit_limits']['daily']['current']);

        $this->api($user)->putJson('/api/profile/responsible-gaming/limits', ['daily' => '500.00'])->assertOk();
        $view = ResponsibleGaming::present(ResponsibleGaming::for($user))['deposit_limits']['daily'];
        $this->assertSame('100.00', $view['current'], 'a raise must not apply immediately');
        $this->assertSame('500.00', $view['pending']);

        $this->travel(25)->hours();
        $this->assertSame('500.00', ResponsibleGaming::present(ResponsibleGaming::for($user))['deposit_limits']['daily']['current']);

        // Removal is also a loosening: delayed.
        ResponsibleGaming::setDepositLimits($user, ['daily' => null]);
        $this->assertSame('500.00', ResponsibleGaming::present(ResponsibleGaming::for($user))['deposit_limits']['daily']['current']);
        $this->travel(25)->hours();
        $this->assertNull(ResponsibleGaming::present(ResponsibleGaming::for($user))['deposit_limits']['daily']['current']);
    }

    public function test_deposit_limit_counts_pending_deposits_and_blocks_server_side(): void
    {
        $user = $this->makeUser();
        ResponsibleGaming::setDepositLimits($user, ['daily' => '100.00']);
        $this->pendingDeposit($user, 'p-1', '80.00');
        $this->configureSuitPay();

        $this->assertNull(ResponsibleGaming::depositBlockReason($user, '20.00'));
        $this->assertNotNull(ResponsibleGaming::depositBlockReason($user, '20.01'));

        $this->api($user)->postJson('/api/wallet/deposit/payment', ['gateway' => 'suitpay', 'amount' => '50'])->assertStatus(403);
    }

    public function test_self_exclusion_blocks_deposits_and_play_and_cannot_be_shortened(): void
    {
        $user = $this->makeUser();
        ResponsibleGaming::exclude($user, '30d');
        $until = ResponsibleGaming::for($user)->excluded_until;

        ResponsibleGaming::exclude($user, '24h');
        $this->assertTrue(ResponsibleGaming::for($user)->excluded_until->equalTo($until), 'an exclusion is never shortened');

        $this->travel(2)->seconds();
        $this->assertNotNull(ResponsibleGaming::depositBlockReason($user, '1.00'));
        $this->api($user)->getJson('/api/profile/responsible-gaming')->assertOk()->assertJsonPath('excluded', true);
        $this->api($user)->putJson('/api/profile/responsible-gaming/limits', ['daily' => '10'])->assertOk();

        $this->api($user)->postJson('/api/profile/responsible-gaming/exclude', ['duration' => '7d'])->assertStatus(422);

        $this->travel(31)->days();
        $this->assertFalse(ResponsibleGaming::isExcluded($user));
    }

    public function test_exclusion_ends_existing_sessions(): void
    {
        $user = $this->makeUser();
        $token = auth('api')->login($user);
        $this->travel(2)->seconds();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/profile/responsible-gaming/exclude', ['duration' => '24h', 'confirm' => true])->assertOk();
        auth('api')->forgetUser();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/profile/responsible-gaming')->assertStatus(401);
    }

    public function test_reality_check_is_clamped(): void
    {
        $user = $this->makeUser();
        $this->assertSame(15, ResponsibleGaming::setRealityCheck($user, 1)->reality_check_minutes);
        $this->assertSame(240, ResponsibleGaming::setRealityCheck($user, 9999)->reality_check_minutes);
        $this->assertNull(ResponsibleGaming::setRealityCheck($user, null)->reality_check_minutes);
    }

    public function test_legal_pages_are_sanitized_localized_and_tenant_scoped(): void
    {
        LegalPage::create(['slug' => 'terms', 'locale' => 'en', 'title' => 'Terms', 'published' => true,
            'body_markdown' => "# Terms\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))"]);

        $res = $this->getJson('/api/legal/terms', ['Accept-Language' => 'sq'])->assertOk()->assertJsonPath('published', true);
        $html = $res->json('html');
        $this->assertStringContainsString('<h1>Terms</h1>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame('en', $res->json('locale'), 'falls back to a published locale');

        $this->getJson('/api/legal/privacy')->assertOk()->assertJsonPath('published', false)->assertJsonMissingPath('html');
        $this->getJson('/api/legal/not-a-page')->assertNotFound();

        $other = Tenant::create(['slug' => 'brand-b', 'name' => 'Brand B', 'status' => Tenant::ACTIVE]);
        Tenancy::runAs($other, fn () => $this->assertNull(LegalPage::resolve('terms', 'en')));
    }

    public function test_analytics_are_computed_from_the_ledger_per_currency(): void
    {
        $user = $this->makeUser();
        $wallet = $this->wallet($user);

        WalletService::credit($wallet, 'balance', '100.00', 'deposit', 'deposits');
        WalletService::debitBet($wallet->fresh(), '10.00');
        WalletService::credit($wallet->fresh(), 'balance', '4.00', 'game.win', 'game');

        $s = Analytics::summary(now()->subDay(), now()->addMinute());
        $c = $s['currencies'][$wallet->currency];
        $this->assertSame('100.00', $c['deposits']);
        $this->assertSame('6.00', $c['ggr']);
        $this->assertSame(1, $c['active_players']);
        $this->assertGreaterThanOrEqual(1, $s['registrations']);

        $other = Tenant::create(['slug' => 'brand-b', 'name' => 'Brand B', 'status' => Tenant::ACTIVE]);
        Tenancy::runAs($other, fn () => $this->assertSame([], Analytics::summary(now()->subDay(), now()->addMinute())['currencies']));
    }
}
