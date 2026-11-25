<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Helpers\Core as Helper;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProviderResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('All providers');
    }

    public static function getModelLabel(): string
    {
        return __('All providers');
    }

    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';



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
                Forms\Components\Section::make('')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('Code'))
                        ->placeholder(__('Code'))
                        ->maxLength(50),
                    Forms\Components\TextInput::make('name')
                        ->placeholder(__('Name'))
                        ->label(__('Name'))
                        ->maxLength(50),
                    Forms\Components\Select::make('distribution')
                        ->label(__('Distribution'))
                        ->placeholder(__('Select the distribution'))
                        ->required()
                        ->options(\Helper::getDistribution()),
                    Forms\Components\TextInput::make('rtp')
                        ->label('RTP')
                        ->numeric()
                        ->default(90),
                    Forms\Components\TextInput::make('views')
                        ->label(__('Views'))
                        ->numeric()
                        ->default(1),
                    Forms\Components\Toggle::make('status')
                        ->label(__('Status'))
                        ->inline(false)
                        ->required(),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('distribution')
                    ->label(__('Distribution'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('rtp')
                    ->label('RTP')
                    ->suffix('%')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('views')
                    ->label(__('Views'))
                    ->numeric()
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
                SelectFilter::make('distribution')
                    ->label(__('Distribution'))
                    ->options(Helper::getDistribution())
                    ->attribute('distribution')
                ,
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => \App\Filament\Admin\Resources\ProviderResource\Pages\ListProviders::route('/'),
//            'create' => Pages\CreateProvider::route('/create'),
//            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}
