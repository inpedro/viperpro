<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\MissionResource\Pages;
use App\Filament\Resources\MissionResource\RelationManagers;
use App\Models\Currency;
use App\Models\GameProvider;
use App\Models\Mission;
use App\Models\Provider;
use App\Models\Wallet;
use Filament\Forms;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MissionResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('Missions');
    }

    public static function getModelLabel(): string
    {
        return __('Missions');
    }

    protected static ?string $model = Mission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';



    protected static ?string $slug = 'centro-missoes';

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('')
                ->schema([
                    Forms\Components\TextInput::make('challenge_name')
                        ->required()
                        ->label(__('Mission name'))
                        ->placeholder(__('Mission name'))
                        ->columnSpanFull()
                        ->maxLength(191),
                    RichEditor::make('challenge_description')
                        ->label(__('Description'))
                        ->columnSpanFull()
                        ->required()
                        ->placeholder(__('Mission description')),

                    RichEditor::make('challenge_rules')
                        ->label(__('Rules'))
                        ->columnSpanFull()
                        ->required()
                        ->placeholder(__('Mission rules')),
                    Select::make('challenge_type')
                        ->default('game')
                        ->label(__('Mission type'))
                        ->required()
                        ->options([
                            'game' => __('Game'),
                            'wallet' => __('Wallet'),
                            'deposit' => __('Deposit'),
                            'affiliate' => __('Affiliate'),
                        ]),
                    Forms\Components\TextInput::make('challenge_link')
                        ->label(__('Mission link'))
                        ->maxLength(191),
                    Forms\Components\DateTimePicker::make('challenge_start_date')
                        ->label(__('Mission start date'))
                        ->required(),
                    Forms\Components\DateTimePicker::make('challenge_end_date')
                        ->label(__('Mission end date'))
                        ->required(),
                    Forms\Components\TextInput::make('challenge_bonus')
                        ->label(__('Bonus amount'))
                        ->required()
                        ->numeric()
                        ->default(0.00),
                    Forms\Components\TextInput::make('challenge_total')
                        ->label(__('Total missions'))
                        ->required()
                        ->numeric()
                        ->default(1),
                    Select::make('challenge_currency')
                        ->label(__('Default currency'))
                        ->required()
                        ->options(Currency::all()->pluck('code', 'id'))
                        ->reactive()
                        ->default(Wallet::where('active', 1)->first()->currency)
                        ->searchable(),
                    Select::make('challenge_provider')
                        ->label(__('Provider'))
                        ->options(Provider::all()->pluck('name', 'id'))
                        ->reactive()
                        ->searchable(),
                    Forms\Components\TextInput::make('challenge_gameid')
                        ->label('Game ID')
                        ->placeholder(__('Game ID (shown in the game list)'))
                        ->columnSpanFull()
                        ->maxLength(191),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('challenge_name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('challenge_type')
                    ->label(__('Type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('challenge_link')
                    ->label(__('Link'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('challenge_start_date')
                    ->label(__('Start date'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('challenge_end_date')
                    ->label(__('End date'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('challenge_bonus')
                    ->label(__('Prize amount'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('challenge_total')
                    ->label(__('Total'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('challenge_currency')
                    ->label(__('Currency'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
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

    /**
     * @return string[]
     */
    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\MissionResource\RelationManagers\UsersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\MissionResource\Pages\ListMissions::route('/'),
            'create' => \App\Filament\Admin\Resources\MissionResource\Pages\CreateMission::route('/create'),
            'edit' => \App\Filament\Admin\Resources\MissionResource\Pages\EditMission::route('/{record}/edit'),
        ];
    }
}
