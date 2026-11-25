<?php

namespace App\Filament\Admin\Resources\UserResource\Widgets;

use App\Models\AffiliateHistory;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class IndicationsTableWidget extends BaseWidget
{
    public User $record;

    public function mount($record)
    {
        $this->record = $record;
    }

    protected static ?string $heading = 'Todas Indicações';

    protected static ?int $navigationSort = -1;

    protected int | string | array $columnSpan = 'full';

    /**
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(AffiliateHistory::query()->where('inviter', $this->record->id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('losses')
                    ->formatStateUsing(fn (AffiliateHistory $record): string => __('Losses: :count, total: :amount', ['count' => $record->losses, 'amount' => \App\Core\Localization\Formatter::tenantCurrencySymbol().' '.$record->losses_amount]))
                    ->label(__('Losses'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('Commission type'))
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('commission_paid')
                    ->label(__('Commission'))
                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
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
