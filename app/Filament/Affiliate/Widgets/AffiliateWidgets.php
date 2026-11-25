<?php

namespace App\Filament\Affiliate\Widgets;

use App\Models\AffiliateHistory;
use App\Models\User;
use App\Models\Wallet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AffiliateWidgets extends BaseWidget
{
    protected static ?int $navigationSort = -2;

    /**
     * @return array|Stat[]
     */
    protected function getCards(): array
    {
        $inviterId      = auth()->user()->id;
        $usersIds       = User::where('inviter', $inviterId)->get()->pluck('id');
        $usersTotal     = User::where('inviter', $inviterId)->count();
        $comissaoTotal  = Wallet::whereIn('user_id', $usersIds)->sum('balance');
        $mycomission    = Wallet::where('user_id', $inviterId)->sum('refer_rewards');

        // \Log::info('inviterId: '.$inviterId);
        // \Log::info('usersIds: '.$usersIds);
        // \Log::info('usersTotal: '.$usersTotal);
        // \Log::info('comissaoTotal: '.$comissaoTotal);

        return [
            Stat::make(__('Available balance'), \Helper::amountFormatDecimal($mycomission))
                ->description(__('Balance available for withdrawal'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make(__('Referred users\' balance'), \Helper::amountFormatDecimal($comissaoTotal))
                ->description(__('Total balance of referred users.'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make(__('Registrations'), $usersTotal)
                ->description(__('Users registered with my link'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()->hasRole('afiliado');
    }
}
