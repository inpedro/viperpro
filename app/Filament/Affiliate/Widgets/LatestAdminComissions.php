<?php

namespace App\Filament\Affiliate\Widgets;

use App\Models\AffiliateHistory;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestAdminComissions extends BaseWidget
{
    protected static ?string $heading = 'Minhas Afiliações';

    protected static ?int $navigationSort = -1;

    protected int | string | array $columnSpan = 'full';

    /**
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(AffiliateHistory::query()->where('inviter', auth()->id()))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('User')),
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('E-mail')),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('Type')),

                Tables\Columns\TextColumn::make('deposited_amount')
                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
                    ->badge()
                    // ->color('danger')
                    ->label(__('Deposits')),
//
//                Tables\Columns\TextColumn::make('losses_amount')
//                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
//                    ->badge()
//                    ->color('danger')
//                    ->label(__('Losses')),
//
//                Tables\Columns\TextColumn::make('receita')
//                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
//                    ->badge()
//                    // ->color('danger')
//                    ->label(__('Revenue')),

                Tables\Columns\TextColumn::make('commission_paid')
                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
                    ->label(__('Commission')),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'pago' => 'success',
                    }),
                Tables\Columns\TextColumn::make('dateHumanReadable')
                    ->label(__('Date')),
                Tables\Columns\TextColumn::make('losses')
                    ->label(__('Total losses'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()->hasRole('afiliado');
    }

}
