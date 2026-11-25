<?php

namespace Tests\Feature\Integrations;

use App\Core\Integrations\HealthStatus;
use App\Core\Integrations\UnsupportedCapability;
use App\Core\Ledger\Models\LedgerJournal;
use App\Core\Money\Money;
use App\Core\Payments\Contracts\Capability;
use App\Core\Payments\Contracts\PaymentGatewayInterface;
use App\Core\Payments\Data\VerificationResult;
use App\Core\Payments\PaymentGateways;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Stripe\ApiRequestor;
use Tests\Feature\Security\SecurityTestCase;

/**
 * Contract every payment gateway addon must satisfy. New gateway addons are picked up
 * automatically (all gateways contributed by bundled addons are installed in setUp).
 */
class GatewayContractTest extends SecurityTestCase
{
    private const CAPABILITIES = [
        Capability::DEPOSIT, Capability::DEPOSIT_VERIFICATION, Capability::SIGNED_WEBHOOKS,
        Capability::AMOUNT_VERIFICATION, Capability::WITHDRAWAL, Capability::WITHDRAWAL_VERIFICATION, Capability::REFUND,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]); // every provider API fails
        ApiRequestor::setHttpClient(new FakeStripeClient());
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /** @return array<string, PaymentGatewayInterface> */
    private function gateways(): array
    {
        $all = PaymentGateways::all();
        $this->assertGreaterThanOrEqual(5, count($all), 'expected the bundled gateway addons to be active');

        return $all;
    }

    public function test_identity_capabilities_and_configuration_reports(): void
    {
        foreach ($this->gateways() as $id => $g) {
            $this->assertSame($id, $g->getIdentifier(), $id);
            $this->assertNotSame('', $g->getName(), $id);
            $this->assertSame([], array_diff($g->getCapabilities(), self::CAPABILITIES), "{$id} declares unknown capabilities");
            foreach (self::CAPABILITIES as $cap) {
                $this->assertSame(in_array($cap, $g->getCapabilities(), true), $g->supports($cap), "{$id} supports({$cap})");
            }
            $this->assertContainsOnly('string', $g->validateConfiguration(), true, $id);
            $this->assertContainsOnly('string', $g->getPayoutMethods(), true, $id);
            $this->assertSame($g->supports(Capability::WITHDRAWAL), $g->getPayoutMethods() !== [], "{$id}: withdrawal capability vs payout methods");
            $this->assertContainsOnly('string', $g->getSupportedCurrencies(), true, $id);
        }
    }

    public function test_unconfigured_gateways_are_disabled_and_fail_closed(): void
    {
        foreach ($this->gateways() as $id => $g) {
            $this->assertNotEmpty($g->validateConfiguration(), "{$id} should report missing configuration");
            $this->assertSame(HealthStatus::DISABLED, $g->getHealthStatus()->status, $id);
            $this->assertFalse($g->getHealthStatus()->isUsable(), $id);
        }
    }

    public function test_unknown_payments_never_verify_as_paid_when_the_provider_is_down(): void
    {
        $this->configureSuitPay();
        foreach ($this->gateways() as $id => $g) {
            $v = $g->verifyDeposit('does-not-exist-123');
            $this->assertInstanceOf(VerificationResult::class, $v, $id);
            $this->assertFalse($v->isPaid(), "{$id} verified an unknown payment as paid");
        }
    }

    public function test_forged_webhooks_never_credit_anything(): void
    {
        $user = $this->makeUser();
        $forgeries = [
            ['idTransaction' => 'x1', 'typeTransaction' => 'PIX', 'statusTransaction' => 'PAID_OUT'],
            ['success' => true, 'payload' => ['id' => 'x2']],
            ['type' => 'payment', 'data' => ['id' => '42']],
            ['payment_id' => 'x3', 'reference' => 'r'],
            ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_x']]],
        ];

        foreach ($this->gateways() as $id => $g) {
            foreach ($forgeries as $body) {
                $request = Request::create("/payments/webhooks/{$id}", 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
                $result = $g->processWebhook($request);
                $this->assertFalse($result->credited, "{$id} credited a forged webhook");
            }
        }

        $this->assertSame(0, LedgerJournal::where('type', 'deposit')->count());
        $this->assertEquals(0, (float) $this->wallet($user)->balance_withdrawal);
    }

    public function test_unsupported_operations_throw_instead_of_pretending(): void
    {
        foreach ($this->gateways() as $id => $g) {
            if (! $g->supports(Capability::REFUND)) {
                try {
                    $g->refund('x', Money::of('1.00', 'EUR'));
                    $this->fail("{$id} pretended to refund");
                } catch (UnsupportedCapability) {
                    $this->addToAssertionCount(1);
                }
            }
            if (! $g->supports(Capability::WITHDRAWAL_VERIFICATION)) {
                try {
                    $g->verifyWithdrawal('x');
                    $this->fail("{$id} pretended to verify a payout");
                } catch (UnsupportedCapability) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_payouts_are_refused_for_methods_the_gateway_does_not_offer(): void
    {
        $w = new Withdrawal();
        $w->forceFill(['id' => 1, 'user_id' => 1, 'amount' => '10.00', 'type' => 'bank', 'status' => 0]);

        foreach ($this->gateways() as $id => $g) {
            $this->assertFalse($g->createWithdrawal($w, 'user'), "{$id} paid out a method it does not offer");
        }
    }
}
