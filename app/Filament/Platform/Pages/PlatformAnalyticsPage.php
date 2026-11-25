<?php

namespace App\Filament\Platform\Pages;

use App\Core\Analytics\Analytics;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use Filament\Pages\Page;

/** Per-casino KPIs (30 days) for the platform operator; each tenant computed in its own context. */
class PlatformAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'analytics';

    protected static string $view = 'filament.pages.platform-analytics-page';

    public static function getNavigationLabel(): string
    {
        return __('Analytics');
    }

    public static function canAccess(): bool
    {
        return PlatformAddonsPage::canAccess();
    }

    public function getRows(): array
    {
        $rows = [];
        foreach (Tenant::orderBy('name')->get() as $tenant) {
            $rows[] = ['tenant' => $tenant, 'summary' => Tenancy::runAs($tenant, fn () => Analytics::summary(now()->subDays(30), now()))];
        }

        return $rows;
    }
}
