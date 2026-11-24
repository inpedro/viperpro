<?php

namespace Tests\Feature\Integrations;

use App\Core\Addons\AddonManager;
use App\Core\Ledger\Models\LedgerJournal;
use App\Core\Money\Money;
use App\Core\Payments\PaymentGateways;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\Tenancy;
use Stripe\ApiRequestor;
use Tests\Feature\Security\SecurityTestCase;

class StripeGatewayTest extends SecurityTestCase
{
    private FakeStripeClient $stripe;
    private const WHSEC = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->stripe = new FakeStripeClient();
        ApiRequestor::setHttpClient($this->stripe);

        app(AddonManager::class)->activateForTenant('payment.stripe');
        app(AddonManager::class)->configure('payment.stripe', ['secret_key' => 'sk_test_abc123', 'webhook_secret' => self::WHSEC]);
        \App\Models\Setting::query()->update(['stripe_is_enable' => 1, 'max_deposit' => 1000]);
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function signedEvent(array $event, string $secret = self::WHSEC, ?int $ts = null): array
    {
        $payload = json_encode($event);
        $ts ??= time();
        $sig = hash_hmac('sha256', "{$ts}.{$payload}", $secret);

        return [$payload, "t={$ts},v1={$sig}"];
    }

    private function postWebhook(array $event, ?string $secret = null, ?int $ts = null)
    {
        [$payload, $header] = $this->signedEvent($event, $secret ?? self::WHSEC, $ts);

        return $this->call('POST', '/payments/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    private function completed(string $id): array
    {
        return ['id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $id, 'object' => 'checkout.session']]];
    }

    public function test_deposit_creates_a_checkout_session_in_the_wallet_currency_without_exposing_keys(): void
    {
        $user = $this->makeUser();

        $res = $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'stripe', 'amount' => '25.50'])->assertOk();

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_1', $res->json('checkout_url'));
        $this->assertStringNotContainsString('sk_test', $res->getContent());
        $req = $this->stripe->requests[0];
        $this->assertSame(2550, (int) $req['params']['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('eur', $req['params']['line_items'][0]['price_data']['currency']);
        $this->assertSame('1', $req['params']['metadata']['tenant_id']);
        $this->assertTrue(collect($req['headers'])->contains(fn ($h) => str_starts_with($h, 'Idempotency-Key: deposit-')));
        $this->assertSame(0, (int) \DB::table('transactions')->where('payment_id', 'cs_test_1')->value('status'));
    }

    public function test_signed_webhook_settles_once_from_the_api_amount(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'stripe', 'amount' => '40'])->assertOk();
        $this->stripe->markPaid('cs_test_1');

        $this->postWebhook($this->completed('cs_test_1'))->assertOk();
        $this->postWebhook($this->completed('cs_test_1'))->assertOk(); // Stripe retry: acknowledged, not re-credited

        $this->assertEquals(40.00, (float) $this->wallet($user)->fresh()->balance_withdrawal);
        $this->assertSame(1, LedgerJournal::where('type', 'deposit')->count());
    }

    public function test_bad_signature_stale_timestamp_and_unpaid_sessions_are_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'stripe', 'amount' => '40'])->assertOk();

        $this->postWebhook($this->completed('cs_test_1'), 'whsec_wrong')->assertStatus(400);
        $this->stripe->markPaid('cs_test_1');
        $this->postWebhook($this->completed('cs_test_1'), null, time() - 3600)->assertStatus(400); // replay outside tolerance
        $this->call('POST', '/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->completed('cs_test_1')))->assertStatus(400);

        $this->assertEquals(0, (float) $this->wallet($user)->fresh()->balance_withdrawal);
    }

    public function test_signed_event_for_an_unpaid_session_does_not_credit(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'stripe', 'amount' => '40'])->assertOk();

        // Signed by the real secret, but the API says the session is unpaid: the event is only a hint.
        $this->postWebhook($this->completed('cs_test_1'))->assertStatus(400);
        $this->assertEquals(0, (float) $this->wallet($user)->fresh()->balance_withdrawal);
    }

    public function test_session_from_another_tenant_is_never_settled(): void
    {
        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'status' => Tenant::ACTIVE]);
        TenantDomain::create(['tenant_id' => $b->id, 'domain' => 'b.test']);
        $user = $this->makeUser();
        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'stripe', 'amount' => '40'])->assertOk();
        $this->stripe->markPaid('cs_test_1');

        Tenancy::runAs($b, function () {
            app(AddonManager::class)->activateForTenant('payment.stripe');
            app(AddonManager::class)->configure('payment.stripe', ['secret_key' => 'sk_test_b', 'webhook_secret' => self::WHSEC]);
            $this->assertFalse(PaymentGateways::get('stripe')->verifyDeposit('cs_test_1')->isPaid());
        });
    }

    public function test_ignored_event_types_are_acknowledged(): void
    {
        $this->postWebhook(['id' => 'evt_x', 'object' => 'event', 'type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]])->assertOk();
        $this->assertSame(0, LedgerJournal::count());
    }

    public function test_refund_reverses_the_ledger_and_compensates_on_failure(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'stripe', 'amount' => '40'])->assertOk();
        $this->stripe->markPaid('cs_test_1');
        $this->postWebhook($this->completed('cs_test_1'))->assertOk();
        $gw = PaymentGateways::get('stripe');

        $this->stripe->failRefunds = true;
        $this->assertFalse($gw->refund('cs_test_1', Money::of('10.00', 'EUR')));
        $this->assertEquals(40.00, (float) $this->wallet($user)->fresh()->balance_withdrawal);

        $this->stripe->failRefunds = false;
        $this->assertTrue($gw->refund('cs_test_1', Money::of('15.00', 'EUR')));
        $this->assertEquals(25.00, (float) $this->wallet($user)->fresh()->balance_withdrawal);
        $this->assertSame(0, \Artisan::call('wallet:reconcile'));
    }
}
