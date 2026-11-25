<?php

namespace Tests\Feature\Integrations;

use App\Core\Security\CallbackLogEntry;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Filament\Admin\Pages\IntegrationsPage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class IntegrationsPageTest extends SecurityTestCase
{
    public function test_admin_sees_health_and_only_their_own_webhook_log(): void
    {
        $b = Tenant::create(['slug' => 'b', 'name' => 'B', 'status' => Tenant::ACTIVE]);
        TenantDomain::create(['tenant_id' => $b->id, 'domain' => 'b.test']);

        $this->postJson('http://localhost/suitpay/callback', ['idTransaction' => 'tenant-a-ref']);
        $this->postJson('http://b.test/payments/webhooks/stripe', ['x' => 1]); // brand B (stripe not active there)

        // Requests set the (static) tenant context; return to brand A for the assertions.
        \App\Core\Tenancy\Tenancy::set(Tenant::findOrFail(1));

        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertSame(['tenant-a-ref'], CallbackLogEntry::query()->pluck('reference')->filter()->values()->all());
        $this->assertSame(1, CallbackLogEntry::count());

        Livewire::test(IntegrationsPage::class)
            ->assertSee('SuitPay')
            ->assertSee('Stripe')
            ->assertSee('Fivers (gold_api)')
            ->assertCanSeeTableRecords(CallbackLogEntry::all());
    }

    public function test_non_admins_cannot_open_it(): void
    {
        $this->actingAs($this->makeUser());
        $this->assertFalse(IntegrationsPage::canAccess());
    }
}
