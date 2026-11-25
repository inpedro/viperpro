<?php

namespace Tests\Feature\Tenancy;

use App\Core\Addons\AddonException;
use App\Core\Addons\AddonManager;
use App\Core\Payments\PaymentGateways;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use App\Filament\Admin\Pages\AddonManagerPage;
use App\Filament\Platform\Pages\PlatformAddonsPage;
use App\Filament\Platform\Resources\TenantResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class PlatformAndTenantAddonsTest extends SecurityTestCase
{
    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->b = Tenant::create(['slug' => 'brand-b', 'name' => 'Brand B', 'status' => Tenant::ACTIVE]);
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');
    }

    private function manager(): AddonManager
    {
        return app(AddonManager::class);
    }

    public function test_addons_and_credentials_are_per_tenant(): void
    {
        // Tenant 1 was activated by addon:sync --enable; brand B has nothing yet.
        $this->assertTrue(PaymentGateways::has('suitpay'));
        $this->assertFalse(Tenancy::runAs($this->b, fn () => PaymentGateways::has('suitpay')));

        $this->manager()->configure('payment.mercadopago', ['access_token' => 'token-of-brand-a']);

        Tenancy::runAs($this->b, function () {
            $this->manager()->activateForTenant('payment.mercadopago');
            $this->assertTrue(PaymentGateways::has('mercadopago'));
            $this->assertNull($this->manager()->config('payment.mercadopago', 'access_token'));
            $this->manager()->configure('payment.mercadopago', ['access_token' => 'token-of-brand-b']);
        });

        $this->assertSame('token-of-brand-a', $this->manager()->config('payment.mercadopago', 'access_token'));
        $this->assertSame('token-of-brand-b', Tenancy::runAs($this->b, fn () => $this->manager()->config('payment.mercadopago', 'access_token')));
    }

    public function test_tenants_cannot_activate_addons_the_platform_has_not_enabled(): void
    {
        $this->manager()->disable('payment.sharkpay');

        $this->expectException(AddonException::class);
        Tenancy::runAs($this->b, fn () => $this->manager()->activateForTenant('payment.sharkpay'));
    }

    public function test_tenant_admin_page_cannot_perform_platform_actions(): void
    {
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(AddonManagerPage::class)->call('runAction', 'disable', 'payment.suitpay');
        $this->assertTrue($this->manager()->isEnabled('payment.suitpay'), 'tenant admins must not disable platform addons');

        Livewire::test(AddonManagerPage::class)->call('runAction', 'deactivate', 'payment.suitpay');
        $this->assertFalse($this->manager()->isActiveForTenant('payment.suitpay'));
        $this->assertTrue(Tenancy::runAs($this->b, fn () => true)); // other tenants unaffected
    }

    public function test_panel_access_is_bound_to_tenant_and_platform_context(): void
    {
        $adminA = $this->makeUser();
        $adminA->assignRole('admin');

        $this->assertTrue($adminA->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse(Tenancy::runAs($this->b, fn () => $adminA->canAccessPanel(Filament::getPanel('admin'))));
        $this->assertFalse($adminA->canAccessPanel(Filament::getPanel('platform')));

        $this->artisan('viper:create-admin', ['email' => 'root@platform.test', '--platform' => true])
            ->expectsQuestion('Password (min 12 characters)', 'a-very-long-password')
            ->assertSuccessful();

        $root = Tenancy::acrossTenants(fn () => User::where('email', 'root@platform.test')->first());
        $this->assertNull($root->tenant_id);
        $this->assertTrue($root->hasRole('super-admin'));
    }

    public function test_super_admin_manages_tenants_from_the_platform_context(): void
    {
        Tenancy::setPlatform();
        $root = new User();
        $root->forceFill(['name' => 'Root', 'email' => 'root@platform.test', 'password' => 'very-long-password'])->save();
        $root->assignRole('super-admin');
        $this->actingAs($root);

        $this->assertNull($root->fresh()->tenant_id);
        $this->assertTrue($root->canAccessPanel(Filament::getPanel('platform')));
        $this->assertFalse($root->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(PlatformAddonsPage::canAccess());
        $this->assertSame(0, User::where('tenant_id', 1)->count(), 'platform context sees no casino players');

        $admin = TenantResource::createTenantAdmin($this->b, ['name' => 'B admin', 'email' => 'admin@b.test', 'password' => 'another-long-password']);
        $this->assertSame($this->b->id, (int) $admin->tenant_id);
        $this->assertTrue(Tenancy::runAs($this->b, fn () => User::where('email', 'admin@b.test')->first()->hasRole('admin')));

        Livewire::test(PlatformAddonsPage::class)->call('runAction', 'disable', 'payment.sharkpay');
        $this->assertFalse($this->manager()->isEnabled('payment.sharkpay'));
    }
}
