<?php

namespace App\Filament\Admin\Resources;

use App\Core\Content\LegalPage;
use App\Core\Localization\Locales;
use App\Filament\Admin\Resources\LegalPageResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class LegalPageResource extends Resource
{
    protected static ?string $model = LegalPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    public static function getNavigationLabel(): string
    {
        return __('Legal pages');
    }

    public static function getModelLabel(): string
    {
        return __('Legal page');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin') && \App\Core\Tenancy\Tenancy::id() !== null;
    }

    public static function form(Form $form): Form
    {
        $locales = array_combine(Locales::enabled(), array_map(fn ($c) => Locales::meta($c)['native'], Locales::enabled()));

        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('slug')->label(__('Page'))->required()
                    ->options(array_map(fn ($l) => __($l), LegalPage::SLUGS)),
                Forms\Components\Select::make('locale')->label(__('Language'))->required()->options($locales)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, callable $get) => $rule
                        ->where('slug', $get('slug'))->where('tenant_id', \App\Core\Tenancy\Tenancy::id())),
                Forms\Components\TextInput::make('title')->label(__('Title'))->required()->maxLength(191),
                Forms\Components\Toggle::make('published')->label(__('Published')),
            ])->columns(4),
            Forms\Components\MarkdownEditor::make('body_markdown')->label(__('Content'))->required()
                ->helperText(__('Markdown. Raw HTML is removed when the page is displayed.'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->label(__('Page'))->formatStateUsing(fn ($s) => __(LegalPage::SLUGS[$s] ?? $s))->sortable(),
                Tables\Columns\TextColumn::make('locale')->label(__('Language'))->badge(),
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\IconColumn::make('published')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLegalPages::route('/'),
            'create' => Pages\CreateLegalPage::route('/create'),
            'edit'   => Pages\EditLegalPage::route('/{record}/edit'),
        ];
    }
}
