<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Filament\Resources\GameResource\RelationManagers;
use App\Models\Game;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GameResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('All games');
    }

    public static function getModelLabel(): string
    {
        return __('All games');
    }

    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';



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
                    Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Select::make('provider_id')
                            ->label(__('Provider'))
                            ->placeholder(__('Select a provider'))
                            ->relationship(name: 'provider', titleAttribute: 'name')
                            ->options(
                                fn($get) => Provider::query()
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                        ,
                        Forms\Components\Select::make('categories')
                            ->label(__('Category'))
                            ->placeholder(__('Select categories for the game'))
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                        ,
                        Forms\Components\TextInput::make('game_server_url')
                            ->label('Server URL')
                            ->placeholder(__('Server URL, if any'))
                            ->maxLength(191),
                        Forms\Components\TextInput::make('game_name')
                            ->label(__('Game name'))
                            ->placeholder(__('Game name'))
                            ->required()
                            ->maxLength(191),
                        Forms\Components\Textarea::make('description')
                            ->label(__('Description'))
                            ->placeholder(__('Game description'))
                            ->autosize(),
                        Forms\Components\Grid::make()
                        ->schema([
                            Forms\Components\TextInput::make('game_id')
                                ->label(__('Game ID'))
                                ->placeholder(__('Game ID'))
                                ->required()
                                ->maxLength(191),
                            Forms\Components\TextInput::make('game_code')
                                ->placeholder(__('Game code'))
                                ->label(__('Game code'))
                                ->required()
                                ->maxLength(191),
                            Forms\Components\TextInput::make('game_type')
                                ->placeholder(__('Game type'))
                                ->label(__('Game type'))
                                ->required()
                                ->maxLength(191),
                        ])->columns(3),
                        Forms\Components\FileUpload::make('cover')
                            ->label(__('Cover image'))
                            ->placeholder(__('Upload the game cover'))
                            ->image()
                            ->columnSpanFull()
                            ->helperText(__('Recommended cover size: 322×322'))
                            ->required(),
                    ]),
                    Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('technology')
                            ->label(__('Technology'))
                            ->placeholder(__('Game technology, e.g. html, java, construct 3'))
                            ->maxLength(191),
                        Forms\Components\TextInput::make('rtp')
                            ->label('RTP')
                            ->placeholder(__('Game RTP'))
                            ->required()
                            ->numeric()
                            ->default(90),
                        Forms\Components\Select::make('distribution')
                            ->label(__('Distribution'))
                            ->placeholder(__('Select the distribution'))
                            ->required()
                            ->options(\Helper::getDistribution()),
                        Forms\Components\TextInput::make('views')
                            ->label(__('Views'))
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('has_lobby')
                            ->required(),
                        Forms\Components\Toggle::make('is_mobile')
                            ->required(),
                        Forms\Components\Toggle::make('has_freespins')
                            ->required(),
                        Forms\Components\Toggle::make('has_tables')
                            ->required(),
                        Forms\Components\Toggle::make('only_demo')
                            ->required(),
                        Forms\Components\Toggle::make('is_featured')
                            ->label(__('Featured')),
                        Forms\Components\Toggle::make('show_home')
                            ->label(__('Show on home page')),
                        Forms\Components\Toggle::make('status')
                            ->label(__('Status'))
                            ->helperText(__('Enable or disable the game'))
                            ->default(true)
                            ->required(),
                    ])

                ])->columns(2)
            ]);
    }

    /**
     * @param Table $table
     * @return Table
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('cover')
                ->label(__('Cover image'))
                //->disk('media')
                ,
                Tables\Columns\TextColumn::make('provider.name')
                    ->label(__('Provider'))
                    ->numeric()
                    ->sortable()
                ,
                Tables\Columns\TextColumn::make('categories.name')
                    ->label(__('Categories'))
                    ->wrap()
                    ->badge()
                ,
                Tables\Columns\TextColumn::make('game_server_url')
                    ->label('Server URL')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('game_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Game ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('game_name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('show_home')
                    ->afterStateUpdated(function ($record, $state) {
                        if($state == 1) {
                            $record->update(['status' => 1]);
                        }
                    })
                    ->label(__('Show on home page')),
                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label(__('Featured')),
                Tables\Columns\TextColumn::make('game_code')
                    ->label(__('Code'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('game_type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('Type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('status')
                    ->label(__('Status')),
                Tables\Columns\TextColumn::make('technology')
                    ->label(__('Technology'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\IconColumn::make('has_lobby')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_mobile')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_freespins')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_tables')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\ToggleColumn::make('only_demo')
                    ->label(__('Demo'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rtp')
                    ->label('RTP')
                    ->suffix('%')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('distribution')
                    ->label(__('Distribution'))
                    ->badge(),
                Tables\Columns\TextColumn::make('views')
                    ->icon('heroicon-o-eye')
                    ->numeric()
                    ->formatStateUsing(fn (Game $record): string => \Helper::formatNumber($record->views))
                    ->sortable(),
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
                SelectFilter::make('categories.name')
                    ->relationship('categories', 'name')
                    ->preload()
                    ->multiple()
                    ->indicator('Categoria')
                    ->searchable(),
//                SelectFilter::make('Categoria')
//                    ->relationship('category', 'name')
//                    ->label(__('Select a category'))
//                    ->indicator('Categoria'),
                SelectFilter::make('Provedor')

                    ->relationship('provider', 'name')
                    ->label(__('Provider'))
                    ->indicator('Provedor'),
                SelectFilter::make('distribution')
                    ->label(__('Distribution'))
                    ->options(\Helper::getDistribution())
                    ->attribute('distribution')
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('Ativar Jogos')->label(__('Enable games'))
                    ->icon('heroicon-m-check')
                    ->requiresConfirmation()
                    ->action(function($records) {
                        return $records->each->update(['status' => 1]);
                    }),
                Tables\Actions\BulkAction::make('Desativar Jogos')->label(__('Disable games'))
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function($records) {
                        return $records->each(function($record) {
                            $record->update(['status' => 0]);
                        });
                    }),
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
            'index' => \App\Filament\Admin\Resources\GameResource\Pages\ListGames::route('/'),
            'create' => \App\Filament\Admin\Resources\GameResource\Pages\CreateGame::route('/create'),
            'edit' => \App\Filament\Admin\Resources\GameResource\Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
