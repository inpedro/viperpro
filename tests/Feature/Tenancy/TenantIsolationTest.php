<?php

namespace Tests\Feature\Tenancy;

use App\Core\Ledger\LedgerService;
use App\Core\Payments\DepositSettlementService;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\Tenancy;
use App\Core\Wallet\WalletService;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Security\SecurityTestCase;

class TenantIsolationTest extends SecurityTestCase
{
    private Tenant $a;
    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Tenant::findOrFail(1);
        TenantDomain::create(['tenant_id' => 1, 'domain' => 'a.test', 'is_primary' => true]);
        $this->b = Tenant::create(['slug' => 'brand-b', 'name' => 'Brand B', 'status' => Tenant::ACTIVE]);
        TenantDomain::create(['tenant_id' => $this->b->id, 'domain' => 'b.test', 'is_primary' => true]);

        // Brand B gets its own settings row (the singleton is per tenant).
        Tenancy::runAs($this->b, fn () => Setting::create([
            'software_name' => 'Brand B', 'currency_code' => 'USD', 'prefix' => '$',
            'min_deposit' => 1, 'max_deposit' => 100, 'min_withdrawal' => 1, 'max_withdrawal' => 100,
            'revshare_percentage' => 10, 'cpa_value' => 5, 'cpa_baseline' => 20, 'disable_rollover' => 1,
        ]));

        config(['tenancy.fallback_to_default' => false]);
    }

    private function inA(\Closure $fn): mixed { return Tenancy::runAs($this->a, $fn); }

    private function inB(\Closure $fn): mixed { return Tenancy::runAs($this->b, $fn); }

    public function test_queries_only_see_the_current_tenant(): void
    {
        $this->inA(fn () => $this->makeUser(['email' => 'same@example.test']));
        $this->inB(fn () => $this->makeUser(['email' => 'same@example.test'])); // same e-mail allowed per tenant

        $this->assertSame(1, $this->inA(fn () => User::count()));
        $this->assertSame(1, $this->inB(fn () => User::count()));
        $this->assertSame(1, $this->inB(fn () => Wallet::count()));
        $this->assertSame('USD', $this->inB(fn () => Wallet::first()->currency));
        $this->assertSame(2, Tenancy::acrossTenants(fn () => User::count()));
    }

    public function test_rows_cannot_be_created_in_or_moved_to_another_tenant(): void
    {
        $user = $this->inA(fn () => $this->makeUser());

        // Mass assignment cannot even express another tenant: tenant_id is ignored and stamped.
        $tx = $this->inB(fn () => Transaction::create(['tenant_id' => 1, 'payment_id' => 'x1', 'user_id' => $user->id, 'price' => 1, 'currency' => 'USD']));
        $this->assertSame($this->b->id, (int) $tx->tenant_id);

        // An explicit attempt is refused.
        $this->inB(function () use ($user) {
            $this->expectException(\LogicException::class);
            (new Transaction())->forceFill(['tenant_id' => 1, 'payment_id' => 'x2', 'user_id' => $user->id, 'price' => 1, 'currency' => 'EUR'])->save();
        });
    }

    public function test_tenant_id_is_immutable(): void
    {
        $user = $this->inA(fn () => $this->makeUser());

        $this->expectException(\LogicException::class);
        $this->inA(fn () => $user->forceFill(['tenant_id' => $this->b->id])->save());
    }

    public function test_jwt_from_one_brand_is_rejected_on_another(): void
    {
        $user  = $this->inA(fn () => $this->makeUser());
        $token = $this->inA(fn () => auth('api')->login($user));

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('http://a.test/api/auth/verify')->assertOk();
        auth('api')->forgetUser();
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('http://b.test/api/auth/verify')->assertStatus(401);
    }

    public function test_credentials_from_one_brand_do_not_log_in_on_another(): void
    {
        $user = $this->inA(fn () => $this->makeUser(['email' => 'p@example.test']));

        $this->postJson('http://b.test/api/auth/login', ['email' => 'p@example.test', 'password' => 'correct-horse-battery'])->assertStatus(400);
        $this->postJson('http://a.test/api/auth/login', ['email' => 'p@example.test', 'password' => 'correct-horse-battery'])->assertOk();
    }

    public function test_payment_callback_on_brand_b_cannot_settle_brand_a_deposit(): void
    {
        $this->configureSuitPay();
        Http::fake(['ws.suitpay.app/*' => Http::response('"PAID_OUT"', 200)]);

        $userA = $this->inA(fn () => $this->makeUser());
        $this->inA(fn () => $this->pendingDeposit($userA, 'tx-a'));

        // Brand B's callback endpoint: the transaction is invisible in B's scope.
        $this->assertFalse($this->inB(fn () => DepositSettlementService::settle('tx-a')));
        $this->assertSame(0, (int) DB::table('transactions')->where('payment_id', 'tx-a')->value('status'));
        $this->assertTrue($this->inA(fn () => DepositSettlementService::settle('tx-a')));
    }

    public function test_ledgers_and_platform_accounts_are_per_tenant(): void
    {
        $this->inA(fn () => WalletService::credit($this->wallet($this->makeUser()), 'balance', '10.00', 'adjustment', 'adjustments'));
        $this->inB(fn () => WalletService::credit(Wallet::where('user_id', $this->makeUser()->id)->first(), 'balance', '7.00', 'adjustment', 'adjustments'));

        $this->assertSame(-1000, $this->inA(fn () => LedgerService::accountBalanceMinor(LedgerService::platformAccount('adjustments', 'EUR'))));
        $this->assertSame(-700, $this->inB(fn () => LedgerService::accountBalanceMinor(LedgerService::platformAccount('adjustments', 'USD'))));
        $this->assertSame(0, $this->inB(fn () => \App\Core\Ledger\Models\LedgerJournal::where('type', 'adjustment')->whereHas('entries', fn ($q) => $q->where('currency', 'EUR'))->count()));
    }

    public function test_settings_cache_is_per_tenant(): void
    {
        Cache::flush();
        $this->assertSame('Test Casino', $this->inA(fn () => \Helper::getSetting()->software_name));
        $this->assertSame('Brand B', $this->inB(fn () => \Helper::getSetting()->software_name));
        $this->assertSame('Test Casino', $this->inA(fn () => \Helper::getSetting()->software_name));
    }

    public function test_unknown_hosts_and_suspended_tenants_are_refused(): void
    {
        $this->getJson('http://unknown.test/api/settings/data')->assertNotFound();

        $this->b->update(['status' => Tenant::SUSPENDED]);
        $this->getJson('http://b.test/api/settings/data')->assertStatus(503);
        $this->getJson('http://a.test/api/settings/data')->assertOk()->assertJsonPath('setting.software_name', 'Test Casino');
    }

    public function test_no_context_means_no_rows(): void
    {
        $this->inA(fn () => $this->makeUser());
        Tenancy::reset();

        $this->assertSame(0, User::count());
        $this->expectException(\LogicException::class);
        $this->makeUser();
    }
}
