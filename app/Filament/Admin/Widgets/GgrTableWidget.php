<?php

namespace App\Filament\Admin\Widgets;

use App\Models\GgrGamesWorldSlot;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class GgrTableWidget extends BaseWidget
{

    protected static ?string $heading = 'GGR World Slot';

    protected static ?int $navigationSort = -1;

    protected int | string | array $columnSpan = 'full';

    /**
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(GgrGamesWorldSlot::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('User')),
                Tables\Columns\TextColumn::make('provider')
                    ->label(__('Provider'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('game')
                    ->label(__('Game'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance_bet')
                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
                    ->label(__('Bet balance')),
                Tables\Columns\TextColumn::make('balance_win')
                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
                    ->label(__('Winnings balance')),
                Tables\Columns\TextColumn::make('dateHumanReadable')
                    ->label(__('Date'))
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label(__('Start date')),
                        DatePicker::make('created_until')->label(__('End date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Criação Inicial ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Criação Final ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ;
    }


    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
