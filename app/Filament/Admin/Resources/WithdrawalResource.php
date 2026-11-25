<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Filament\Resources\WithdrawalResource\RelationManagers;
use App\Models\User;
use App\Models\Withdrawal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class WithdrawalResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('Withdrawals');
    }

    public static function getModelLabel(): string
    {
        return __('Withdrawals');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }


    protected static ?string $model = Withdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';




    protected static ?string $slug = 'todos-saques';

    protected static ?int $navigationSort = 3;

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @return string[]
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['type', 'bank_info', 'user.name', 'user.last_name', 'user.cpf', 'user.phone',  'user.email'];
    }

    /**
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 0)->count();
    }

    /**
     * @return string|array|null
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::where('status', 0)->count() > 5 ? 'success' : 'warning';
    }

    /**
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Register withdrawal'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('Users'))
                            ->placeholder(__('Select a user'))
                            ->relationship(name: 'user', titleAttribute: 'name')
                            ->options(
                                fn($get) => User::query()
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label(__('Amount'))
                            ->required()
                            ->default(0.00),
                        Forms\Components\TextInput::make('type')
                            ->label(__('Type'))
                            ->required()
                            ->maxLength(191),
                        Forms\Components\FileUpload::make('proof')
                            ->label(__('Receipt'))
                            ->placeholder(__('Upload the receipt image'))
                            ->image()
                            ->columnSpanFull()
                            ->required(),
                        Forms\Components\Toggle::make('status')
                            ->required(),
                    ])
            ]);
    }

    /**
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->searchable(['users.name', 'users.last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (Withdrawal $record): string => $record->symbol . ' ' . $record->amount)
                    ->sortable(),
                Tables\Columns\TextColumn::make('pix_type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn (string $state): string => \Helper::formatPixType($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('pix_key')
                    ->label(__('Pix key')),
                Tables\Columns\TextColumn::make('bank_info')
                    ->label(__('Bank details')),
                Tables\Columns\TextColumn::make('proof')
                    ->label(__('Receipt'))
                    ->html()
                    ->formatStateUsing(fn (string $state): string => '<a href="'.url('storage/'.$state).'" target="_blank">Baixar</a>'),
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
                    ->visible(fn (Withdrawal $withdrawal): bool => (int) $withdrawal->status === \App\Core\Payments\WithdrawalService::PENDING)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Withdrawal $withdrawal) => 'Cancel this withdrawal of '.\Helper::amountFormatDecimal($withdrawal->amount).' and return the funds to the player?')
                    ->action(function (Withdrawal $withdrawal) {
                        \App\Filament\Admin\Support\WithdrawalActions::cancel($withdrawal->id, 'user');
                    }),
                Action::make('approve_payment')
                    ->label(__('Pay out'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Withdrawal $withdrawal): bool => (int) $withdrawal->status === \App\Core\Payments\WithdrawalService::PENDING)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Withdrawal $withdrawal) => 'Send a payout of '.\Helper::amountFormatDecimal($withdrawal->amount).' through the default gateway?')
                    ->action(function (Withdrawal $withdrawal) {
                        \App\Filament\Admin\Support\WithdrawalActions::approve($withdrawal->id, 'user');
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }



    /**
     * @return array|\Filament\Resources\RelationManagers\RelationGroup[]|\Filament\Resources\RelationManagers\RelationManagerConfiguration[]|string[]
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\WithdrawalResource\Pages\ListWithdrawals::route('/'),
            'create' => \App\Filament\Admin\Resources\WithdrawalResource\Pages\CreateWithdrawal::route('/create'),
            'edit' => \App\Filament\Admin\Resources\WithdrawalResource\Pages\EditWithdrawal::route('/{record}/edit'),
        ];
    }
}
