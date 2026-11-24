<?php

namespace App\Filament\Admin\Pages;

use App\Core\Analytics\Analytics;
use App\Core\Tenancy\Tenancy;
use Filament\Pages\Page;

class AnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'analytics';

    protected static string $view = 'filament.pages.analytics-page';

    public int $days = 30;

    public static function getNavigationLabel(): string
    {
        return __('Analytics');
    }

    public function getTitle(): string
    {
        return __('Analytics');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin') && Tenancy::id() !== null;
    }

    public function getSummary(): array
    {
        $days = in_array($this->days, [1, 7, 30, 90], true) ? $this->days : 30;

        return Analytics::summary(now()->subDays($days), now());
    }

    public function getDaily(): array
    {
        return Analytics::daily(14);
    }
}
