<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class SecurityTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Setting::query()->insert([
            'software_name'   => 'Test Casino',
            'currency_code'   => 'EUR',
            'prefix'          => '€',
            'min_deposit'     => 1,
            'max_deposit'     => 10000,
            'min_withdrawal'  => 1,
            'max_withdrawal'  => 10000,
            'initial_bonus'   => 0,
            'rollover'        => 0,
            'rollover_deposit'=> 0,
            'disable_rollover'=> 1,
            'default_gateway' => 'suitpay',
            'revshare_percentage' => 20,
            'cpa_value' => 10,
            'cpa_baseline' => 40,
        ]);
    }

    protected function makeUser(array $attributes = []): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'name'     => 'Player '.uniqid(),
            'email'    => uniqid('p', true).'@example.test',
            'password' => 'correct-horse-battery',
        ], $attributes))->save();

        return $user->fresh();
    }

    protected function wallet(User $user): Wallet
    {
        return Wallet::where('user_id', $user->id)->firstOrFail();
    }

    protected function pendingDeposit(User $user, string $paymentId, string $amount = '50.00', string $currency = 'EUR'): Transaction
    {
        DB::table('deposits')->insert([
            'payment_id' => $paymentId,
            'user_id'    => $user->id,
            'amount'     => $amount,
            'type'       => 'pix',
            'currency'   => $currency,
            'symbol'     => '€',
            'status'     => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Transaction::create([
            'payment_id'     => $paymentId,
            'user_id'        => $user->id,
            'payment_method' => 'pix',
            'price'          => $amount,
            'currency'       => $currency,
            'status'         => 0,
            'accept_bonus'   => 0,
        ]);
    }

    protected function configureSuitPay(): void
    {
        DB::table('gateways')->insert([
            'suitpay_uri'            => 'https://ws.suitpay.app/api/v1/',
            'suitpay_cliente_id'     => 'ci-test',
            'suitpay_cliente_secret' => 'cs-test',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }
}
