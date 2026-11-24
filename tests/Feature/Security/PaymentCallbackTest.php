<?php

namespace Tests\Feature\Security;

use App\Core\Payments\DepositSettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentCallbackTest extends SecurityTestCase
{
    public function test_forged_suitpay_callback_does_not_credit_when_gateway_says_unpaid(): void
    {
        $this->configureSuitPay();
        $user = $this->makeUser();
        $this->pendingDeposit($user, 'tx-1');

        Http::fake(['ws.suitpay.app/*' => Http::response('"WAITING_FOR_APPROVAL"', 200)]);

        $this->postJson('/suitpay/callback', [
            'idTransaction' => 'tx-1', 'typeTransaction' => 'PIX', 'statusTransaction' => 'PAID_OUT',
        ])->assertStatus(400);

        $this->assertEquals(0, (float) $this->wallet($user)->balance_withdrawal);
        $this->assertSame(0, (int) DB::table('transactions')->where('payment_id', 'tx-1')->value('status'));
        $this->assertSame('rejected', DB::table('callback_logs')->where('source', 'suitpay')->value('outcome'));
    }

    public function test_verified_callback_credits_exactly_once_even_when_replayed(): void
    {
        $this->configureSuitPay();
        $user = $this->makeUser();
        $this->pendingDeposit($user, 'tx-2', '50.00');

        Http::fake(['ws.suitpay.app/*' => Http::response('"PAID_OUT"', 200)]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/suitpay/callback', ['idTransaction' => 'tx-2']);
        }

        $this->assertEquals(50.00, (float) $this->wallet($user)->balance_withdrawal);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'consult-status-transaction')
            && $req->header('ci')[0] === 'ci-test');
    }

    public function test_settlement_rejects_amount_and_currency_mismatch(): void
    {
        $user = $this->makeUser();
        $this->pendingDeposit($user, 'tx-3', '50.00', 'EUR');

        $this->assertFalse(DepositSettlementService::settle('tx-3', '5.00'));
        $this->assertFalse(DepositSettlementService::settle('tx-3', '50.00', 'USD'));
        $this->assertTrue(DepositSettlementService::settle('tx-3', '50.00', 'EUR'));
        $this->assertFalse(DepositSettlementService::settle('tx-3', '50.00', 'EUR'));
        $this->assertEquals(50.00, (float) $this->wallet($user)->balance_withdrawal);
    }

    public function test_digitopay_never_sends_credentials_to_untrusted_endpoint(): void
    {
        DB::table('gateways')->insert([
            'digitopay_uri'            => 'https://si5n56mrnjzvt5gr2f536ildr40sqzke.lambda-url.sa-east-1.on.aws/',
            'digitopay_cliente_id'     => 'id',
            'digitopay_cliente_secret' => 'secret',
        ]);
        $user = $this->makeUser();
        $this->pendingDeposit($user, 'dp-1');
        Http::fake();

        $this->postJson('/digitopay/callback', ['success' => true, 'payload' => ['id' => 'dp-1']])->assertStatus(400);

        Http::assertNothingSent();
        $this->assertEquals(0, (float) $this->wallet($user)->balance_withdrawal);
    }

    public function test_mercadopago_checks_amount_reported_by_api(): void
    {
        DB::table('gateways')->insert(['mp_access_token' => 'APP_USR-test']);
        $user = $this->makeUser();
        $this->pendingDeposit($user, '123456', '50.00', 'EUR');

        Http::fake(['api.mercadopago.com/v1/payments/123456' => Http::response([
            'status' => 'approved', 'transaction_amount' => 1.00, 'currency_id' => 'EUR',
        ])]);

        // Authentic but not creditable (amount mismatch): acknowledged so the gateway stops retrying,
        // never credited, logged for manual review.
        $this->postJson('/mercadopago/callback', ['type' => 'payment', 'data' => ['id' => '123456']])->assertStatus(200);
        $this->assertEquals(0, (float) $this->wallet($user)->balance_withdrawal);
        $this->assertSame('ignored', \Illuminate\Support\Facades\DB::table('callback_logs')->where('source', 'mercadopago')->latest('id')->value('outcome'));
    }
}
