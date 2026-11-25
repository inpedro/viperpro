<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\AffiliateWithdrawResource\Pages;
use App\Models\AffiliateWithdraw;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class AffiliateWithdrawResource extends Resource
{
    protected static ?string $model = AffiliateWithdraw::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('afiliado') || auth()->user()->hasRole('admin');
    }

    /**
     * @return string
     */
    public static function getNavigationLabel(): string
    {
        return auth()->user()->hasRole('afiliado') ? __('My withdrawals') : __('Affiliate withdrawals');
    }

    /**
     * @return string
     */
    public static function getModelLabel(): string
    {
        return auth()->user()->hasRole('afiliado') ? __('My withdrawals') : __('Affiliate withdrawals');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    /**
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->query(auth()->user()->hasRole('afiliado') ? AffiliateWithdraw::query()->where('user_id', auth()->id()) : AffiliateWithdraw::query())
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('E-mail'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (AffiliateWithdraw $record): string => $record->symbol . ' ' . $record->amount)
                    ->sortable(),
                Tables\Columns\TextColumn::make('pix_type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn (string $state): string => \Helper::formatPixType($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('pix_key')
                    ->label(__('Pix key')),
                Tables\Columns\TextColumn::make('bank_info')
                    ->label(__('Bank details'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('proof')
                    ->label(__('Receipt'))
                    ->html()
                    ->formatStateUsing(fn (string $state): string => '<a href="'.url('storage/'.$state).'" target="_blank">Baixar</a>')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('deny_payment')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->visible(fn (AffiliateWithdraw $withdrawal): bool => (int) $withdrawal->status === \App\Core\Payments\WithdrawalService::PENDING)
                    ->requiresConfirmation()
                    ->modalDescription(fn (AffiliateWithdraw $withdrawal) => 'Cancel this withdrawal of '.\Helper::amountFormatDecimal($withdrawal->amount).' and return the funds to the player?')
                    ->action(function (AffiliateWithdraw $withdrawal) {
                        \App\Filament\Admin\Support\WithdrawalActions::cancel($withdrawal->id, 'affiliate');
                    }),
                Action::make('approve_payment')
                    ->label(__('Pay out'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (AffiliateWithdraw $withdrawal): bool => (int) $withdrawal->status === \App\Core\Payments\WithdrawalService::PENDING)
                    ->requiresConfirmation()
                    ->modalDescription(fn (AffiliateWithdraw $withdrawal) => 'Send a payout of '.\Helper::amountFormatDecimal($withdrawal->amount).' through the default gateway?')
                    ->action(function (AffiliateWithdraw $withdrawal) {
                        \App\Filament\Admin\Support\WithdrawalActions::approve($withdrawal->id, 'affiliate');
                    }),
                //Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\AffiliateWithdrawResource\Pages\ListAffiliateWithdraws::route('/'),
        ];
    }
}
