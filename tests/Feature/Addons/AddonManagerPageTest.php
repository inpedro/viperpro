<?php

namespace Tests\Feature\Addons;

use App\Filament\Admin\Pages\AddonManagerPage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class AddonManagerPageTest extends SecurityTestCase
{
    public function test_admin_can_view_disable_and_configure_without_secrets_reaching_the_browser(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        app(\App\Core\Addons\AddonManager::class)->configure('payment.suitpay', [
            'api_url' => 'https://ws.suitpay.app/api/v1/', 'client_id' => 'ci', 'client_secret' => 'very-secret-cs',
        ]);

        $page = Livewire::test(AddonManagerPage::class)
            ->assertSee('payment.suitpay')
            ->call('openConfig', 'payment.suitpay')
            ->assertSet('configData.client_secret', null)
            ->assertDontSee('very-secret-cs');

        $page->set('configData.client_id', 'ci-2')->call('saveConfig');
        $this->assertSame('very-secret-cs', app(\App\Core\Addons\AddonManager::class)->config('payment.suitpay', 'client_secret'));
        $this->assertSame('ci-2', app(\App\Core\Addons\AddonManager::class)->config('payment.suitpay', 'client_id'));

        $page->call('runAction', 'disable', 'payment.suitpay');
        $this->assertFalse(\App\Core\Payments\PaymentGateways::has('suitpay'));
    }

    public function test_non_admins_cannot_open_the_page(): void
    {
        $this->actingAs($this->makeUser());
        $this->assertFalse(AddonManagerPage::canAccess());
    }
}
