<?php

namespace App\Livewire;

use App\Models\SuitPayPayment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestPixPayments extends BaseWidget
{
    protected static ?string $heading = 'Pagamentos Realizados';

    protected static ?int $navigationSort = -1;

    protected int | string | array $columnSpan = 'full';

    /**
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(SuitPayPayment::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('payment_id')
                    ->label(__('Payment ID')),
                Tables\Columns\TextColumn::make('pix_key')
                    ->label(__('Pix key')),
                Tables\Columns\TextColumn::make('pix_type')
                    ->label(__('Key type')),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn () => \App\Core\Localization\Formatter::tenantCurrency())
                    ->label(__('Amount')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'pago' => 'success',
                    }),
                Tables\Columns\TextColumn::make('dateHumanReadable')
                    ->label(__('Date'))
            ]);
    }

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        //return auth()->user()->hasRole('admin');
        return true;
    }

}
