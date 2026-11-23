<?php

namespace App\Filament\Platform\Resources;

use App\Core\Audit\AuditLog;
use App\Filament\Platform\Resources\AuditLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit log';

    public static function canAccess(): bool
    {
        return \App\Filament\Platform\Pages\PlatformAddonsPage::canAccess();
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('tenant_id')->label('Tenant')->placeholder('platform'),
                Tables\Columns\TextColumn::make('actor_id')->label('Actor'),
                Tables\Columns\TextColumn::make('action')->searchable()->badge(),
                Tables\Columns\TextColumn::make('subject_type'),
                Tables\Columns\TextColumn::make('subject_id')->searchable(),
                Tables\Columns\TextColumn::make('ip'),
                Tables\Columns\TextColumn::make('meta')->formatStateUsing(fn ($state) => json_encode($state))->limit(80),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')->label('Tenant')
                    ->options(fn () => \App\Core\Tenancy\Models\Tenant::pluck('name', 'id')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
