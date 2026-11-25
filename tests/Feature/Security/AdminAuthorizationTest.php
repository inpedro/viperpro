<?php

namespace Tests\Feature\Security;

use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

class AdminAuthorizationTest extends SecurityTestCase
{
    public function test_affiliates_cannot_access_admin_panel(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('afiliado', 'web');

        $affiliate = $this->makeUser();
        $affiliate->assignRole('afiliado');
        $admin = $this->makeUser();
        $admin->assignRole('admin');

        $this->assertFalse($affiliate->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($affiliate->canAccessPanel(Filament::getPanel('affiliate')));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_public_get_withdrawal_routes_are_gone(): void
    {
        foreach (['suitpay', 'digitopay', 'sharkpay', 'mercadopago'] as $gw) {
            $this->assertFalse(\Route::has($gw.'.withdrawal'));
            $this->assertFalse(\Route::has($gw.'.cancelwithdrawal'));
        }
    }

}
