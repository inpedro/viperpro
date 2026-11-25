<?php

namespace App\Filament\Platform\Pages;

use App\Core\Ledger\ReconciliationRun;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use Filament\Pages\Page;

/** Latest reconciliation result of every casino. */
class PlatformReconciliationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $slug = 'reconciliation';

    protected static string $view = 'filament.pages.platform-reconciliation-page';

    public static function getNavigationLabel(): string
    {
        return __('Reconciliation');
    }

    public function getTitle(): string
    {
        return __('Reconciliation');
    }

    public static function canAccess(): bool
    {
        return PlatformAddonsPage::canAccess();
    }

    public function getRows(): array
    {
        return Tenant::orderBy('name')->get()->map(fn (Tenant $tenant) => [
            'tenant' => $tenant,
            'run' => Tenancy::runAs($tenant, fn () => ReconciliationRun::query()->whereNotNull('finished_at')->latest('id')->first()),
        ])->all();
    }
}
