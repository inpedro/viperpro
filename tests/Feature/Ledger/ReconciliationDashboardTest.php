<?php

namespace Tests\Feature\Ledger;

use App\Core\Ledger\Reconciliation;
use App\Core\Ledger\ReconciliationRun;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use App\Core\Wallet\WalletService;
use App\Filament\Admin\Pages\ReconciliationPage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\SecurityTestCase;

class ReconciliationDashboardTest extends SecurityTestCase
{
    public function test_runs_are_recorded_with_mismatch_details_and_scoped_to_the_casino(): void
    {
        $user = $this->makeUser();
        $wallet = $this->wallet($user);
        WalletService::credit($wallet, 'balance', '50.00', 'deposit', 'deposits');

        $clean = Reconciliation::run('manual', open: true);
        $this->assertTrue($clean->passed());
        $this->assertGreaterThan(0, $clean->buckets_checked);

        // A balance changed outside the ledger (e.g. a manual SQL edit).
        DB::table('wallets')->where('id', $wallet->id)->update(['balance' => 75]);
        $dirty = Reconciliation::run('manual');
        $this->assertFalse($dirty->passed());
        $this->assertSame(1, $dirty->mismatches);
        $this->assertSame(['wallet_id' => $wallet->id, 'user_id' => $user->id, 'bucket' => 'balance',
            'projection' => '75.00', 'ledger' => '50.00', 'difference' => '25.00'], $dirty->details[0]);

        // Another casino sees neither the wallet nor these runs.
        $b = Tenant::create(['slug' => 'brand-b', 'name' => 'Brand B', 'status' => Tenant::ACTIVE]);
        Tenancy::runAs($b, function () {
            $this->assertSame(0, ReconciliationRun::count());
            $run = Reconciliation::run('manual');
            $this->assertTrue($run->passed());
            $this->assertSame(0, $run->mismatches);
        });
    }

    public function test_admin_can_run_it_from_the_dashboard(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = $this->makeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ReconciliationPage::class)->callAction('run')->assertNotified();

        $run = ReconciliationRun::latest('id')->first();
        $this->assertSame('manual', $run->trigger);
        $this->assertTrue(DB::table('audit_logs')->where('action', 'ledger.reconciliation_run')->exists());

        // Rate limited: a second run within a minute does nothing.
        Livewire::test(ReconciliationPage::class)->callAction('run');
        $this->assertSame(1, ReconciliationRun::count());
    }
}
