<?php

namespace Tests\Feature\Integrations;

use App\Core\Games\GameProviders;
use App\Core\Integrations\HealthStatus;
use App\Core\Integrations\UnsupportedCapability;
use App\Core\Money\Money;
use App\Core\Payments\Contracts\PaymentGatewayInterface;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Security\SecurityTestCase;

class IntegrationContractsTest extends SecurityTestCase
{
    public function test_every_registered_gateway_implements_the_contract_and_is_disabled_when_unconfigured(): void
    {
        $this->assertNotEmpty(PaymentGateways::all());

        foreach (PaymentGateways::all() as $id => $gateway) {
            $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
            $this->assertSame($id, $gateway->getIdentifier());
            $this->assertNotEmpty($gateway->validateConfiguration(), $id);
            $this->assertSame(HealthStatus::DISABLED, $gateway->getHealthStatus()->status, $id);
        }
    }

    public function test_configured_gateway_reports_unverified_not_ok(): void
    {
        $this->configureSuitPay();
        $this->assertSame(HealthStatus::UNVERIFIED, PaymentGateways::get('suitpay')->getHealthStatus()->status);
    }

    public function test_unimplemented_capabilities_throw_instead_of_pretending(): void
    {
        $this->expectException(UnsupportedCapability::class);
        PaymentGateways::get('suitpay')->refund('x', Money::of('1.00', 'EUR'));
    }

    public function test_mercadopago_verification_maps_status_amount_and_currency(): void
    {
        DB::table('gateways')->insert(['mp_access_token' => 'APP_USR-test']);
        Http::fake(['api.mercadopago.com/v1/payments/42' => Http::response(['status' => 'approved', 'transaction_amount' => 12.5, 'currency_id' => 'EUR'])]);

        $v = PaymentGateways::get('mercadopago')->verifyDeposit('42');

        $this->assertSame(VerificationResult::PAID, $v->status);
        $this->assertSame('12.50', $v->amount);
        $this->assertSame('EUR', $v->currency);
        $this->assertSame(VerificationResult::UNKNOWN, PaymentGateways::get('mercadopago')->verifyDeposit('not-numeric')->status);
    }

    public function test_deposit_endpoint_rejects_unknown_and_unconfigured_gateways(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'evilpay', 'amount' => 10])->assertStatus(422);
        $this->actingAs($user, 'api')->postJson('/api/wallet/deposit/payment', ['gateway' => 'digitopay', 'amount' => 10])->assertStatus(422);
    }

    public function test_game_providers_are_fail_closed_descriptors(): void
    {
        foreach (GameProviders::all() as $provider) {
            $this->assertSame(HealthStatus::DISABLED, $provider->getHealthStatus()->status, $provider->getIdentifier());
        }

        config(['game_providers.callbacks.fivers.allowed_ips' => ['127.0.0.1']]);
        $fivers = GameProviders::get('fivers');
        $this->assertSame(HealthStatus::UNVERIFIED, $fivers->getHealthStatus()->status);
        $this->assertNull($fivers->validateCallback(request()));

        $this->expectException(UnsupportedCapability::class);
        $fivers->processBet(request());
    }
}
