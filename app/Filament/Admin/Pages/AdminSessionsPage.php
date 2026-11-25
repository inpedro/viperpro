<?php

namespace App\Filament\Admin\Pages;

use App\Core\Audit\Audit;
use App\Core\Security\AdminSession;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Staff sessions of this casino (or platform), with revocation. */
class AdminSessionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $slug = 'staff-sessions';

    protected static string $view = 'filament.pages.admin-sessions-page';

    public static function getNavigationLabel(): string
    {
        return __('Staff sessions');
    }

    public function getTitle(): string
    {
        return __('Staff sessions');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(['admin', 'super-admin']);
    }

    public function table(Table $table): Table
    {
        $current = hash('sha256', session()->getId());

        return $table
            ->query(AdminSession::query()->with('user')->whereNull('revoked_at'))
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.email')->label(__('Email'))->searchable(),
                Tables\Columns\TextColumn::make('panel')->badge(),
                Tables\Columns\TextColumn::make('ip'),
                Tables\Columns\TextColumn::make('user_agent')->limit(50),
                Tables\Columns\IconColumn::make('mfa_verified')->label('MFA')->boolean(),
                Tables\Columns\TextColumn::make('last_seen_at')->since()->label(__('Last seen')),
                Tables\Columns\TextColumn::make('session_hash')->label('')
                    ->formatStateUsing(fn ($state) => $state === $current ? __('This session') : ''),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')->label(__('Revoke'))->color('danger')->requiresConfirmation()
                    ->visible(fn (AdminSession $s) => $s->session_hash !== $current)
                    ->action(function (AdminSession $s) {
                        $s->forceFill(['revoked_at' => now()])->save();
                        Audit::record('security.session_revoked', 'user', $s->user_id, ['session' => $s->id]);
                    }),
            ]);
    }
}
