<?php

namespace App\Filament\Admin\Widgets;

use App\Models\GgrGamesWorldSlot;
use App\Traits\Providers\WorldSlotTrait;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GGROverview extends BaseWidget
{
    use WorldSlotTrait;

    protected function getStats(): array
    {
        $balance = self::getWorldSlotBalance();
        $creditoGastos = GgrGamesWorldSlot::sum('balance_bet');
        $totalPartidas = GgrGamesWorldSlot::count();

        return [
            Stat::make(__('Fivers credits'), ($balance ?? '0'))
                ->description(__('Current World Slot balance'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7,3,4,5,6,3,5,3]),
            Stat::make(__('Fivers credits spent'), \Helper::amountFormatDecimal($creditoGastos))
                ->description(__('Credits spent by users'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7,3,4,5,6,3,5,3]),
            Stat::make(__('Total Fivers games'), $totalPartidas)
                ->description(__('Total World Slot games'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7,3,4,5,6,3,5,3]),
        ];
    }

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
