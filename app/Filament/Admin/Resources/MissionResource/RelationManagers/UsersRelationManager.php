<?php

namespace App\Filament\Admin\Resources\MissionResource\RelationManagers;

use App\Models\CasinoCategory;
use App\Models\Mission;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    /**
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
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
                    ->columnSpanFull(),

                Forms\Components\Select::make('mission_id')
                    ->label(__('Mission'))
                    ->placeholder(__('Select a user'))
                    ->relationship(name: 'mission', titleAttribute: 'challenge_name')
                    ->options(
                        fn($get) => Mission::query()
                            ->pluck('challenge_name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('status')->string()
            ]);
    }

    /**
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Name')),
                Tables\Columns\TextColumn::make('mission.challenge_name')
                    ->label(__('Mission')),
                Tables\Columns\ToggleColumn::make('status')
                    ->label(__('Status')),
                Tables\Columns\TextColumn::make('created_at')
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
