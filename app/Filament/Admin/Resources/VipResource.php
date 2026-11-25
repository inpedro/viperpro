<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\VipResource\Pages;
use App\Filament\Resources\VipResource\RelationManagers;
use App\Models\Vip;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VipResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('VIP');
    }

    public static function getModelLabel(): string
    {
        return __('VIP');
    }

    protected static ?string $model = Vip::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';



    protected static ?string $slug = 'vip';

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('VIP'))
                    ->description(__('Set up your VIP bonus levels'))
                    ->schema([
                        FileUpload::make('bet_symbol')
                            ->label(__('Symbol'))
                            ->placeholder(__('Upload a symbol'))
                            ->image()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('bet_level')
                            ->label(__('Level'))
                            ->required()
                            //->readOnly(fn (string $context): bool => $context === 'create')
                            ->placeholder(__('Keep the numbered list in order'))
//                            ->default(function() {
//                                $ultimoRegistro = Vip::latest()->first();
//                                return $ultimoRegistro ? $ultimoRegistro->id + 1 : 1;
//                            })
                        ,
                        Forms\Components\TextInput::make('bet_required')
                            ->label(__('Required wager'))
                            ->required()
                            ->placeholder(__('Wager required to win the prize'))
                            ->numeric(),
                        Forms\Components\Select::make('bet_period')
                            ->label(__('Period'))
                            ->options([
                                'weekly' => __('Weekly'),
                                'monthly' => __('Monthly'),
                                'yearly' => __('Yearly'),
                            ]),
                        Forms\Components\TextInput::make('bet_bonus')
                            ->label(__('Bonus'))
                            ->placeholder(__('Total bonus'))
                            ->required()
                            ->numeric(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('bet_symbol')
                    ->label(__('Image')),
                Tables\Columns\TextColumn::make('bet_level')
                    ->label(__('Level'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bet_required')
                    ->label(__('Required wager'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bet_period')
                    ->label(__('Period'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('bet_bonus')
                    ->label(__('Bonus'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => \App\Filament\Admin\Resources\VipResource\Pages\ListVips::route('/'),
            'create' => \App\Filament\Admin\Resources\VipResource\Pages\CreateVip::route('/create'),
            'edit' => \App\Filament\Admin\Resources\VipResource\Pages\EditVip::route('/{record}/edit'),
        ];
    }
}
