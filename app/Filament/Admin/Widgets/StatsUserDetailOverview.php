<?php

namespace App\Filament\Admin\Widgets;

use App\Helpers\Core as Helper;
use App\Models\AffiliateHistory;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsUserDetailOverview extends BaseWidget
{

    public User $record;

    public function mount($record)
    {
       $this->record = $record;
    }

    /**
     * @return array|Stat[]
     */
    protected function getStats(): array
    {
        $totalGanhos = Order::where('user_id', $this->record->id)->where('type', 'win')->sum('amount');
        $totalPerdas = Order::where('user_id', $this->record->id)->where('type', 'loss')->sum('amount');
        $totalAfiliados = AffiliateHistory::where('inviter', $this->record->id)->sum('commission_paid');

        return [
            Stat::make(__('Total winnings'), Helper::amountFormatDecimal(Helper::formatNumber($totalGanhos)))
                ->description(__('Total platform earnings'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make(__('Total losses'), Helper::amountFormatDecimal(Helper::formatNumber($totalPerdas)))
                ->description(__('Total platform losses'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make(__('Affiliate earnings'), Helper::amountFormatDecimal(Helper::formatNumber($totalAfiliados)))
                ->description(__('Total affiliate earnings'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
