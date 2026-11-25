<?php

namespace App\Filament\Admin\Pages;

use App\Core\Audit\Audit;
use App\Core\Ledger\Reconciliation;
use App\Core\Ledger\ReconciliationRun;
use App\Core\Tenancy\Tenancy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;

/** Wallet-vs-ledger reconciliation of this casino: latest result, mismatches, history, run now. */
class ReconciliationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $slug = 'reconciliation';

    protected static string $view = 'filament.pages.reconciliation-page';

    public static function getNavigationLabel(): string
    {
        return __('Reconciliation');
    }

    public function getTitle(): string
    {
        return __('Reconciliation');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin') && Tenancy::id() !== null;
    }

    public function getLatest(): ?ReconciliationRun
    {
        return ReconciliationRun::query()->whereNotNull('finished_at')->latest('id')->first();
    }

    public function getHistory()
    {
        return ReconciliationRun::query()->whereNotNull('finished_at')->latest('id')->limit(24)->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->label(__('Run now'))
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription(__('Compares every wallet with the ledger. Read-only: nothing is corrected automatically.'))
                ->action(function () {
                    $key = 'reconcile:'.Tenancy::id();
                    if (RateLimiter::tooManyAttempts($key, 1)) {
                        Notification::make()->title(__('A run was started less than a minute ago.'))->warning()->send();

                        return;
                    }
                    RateLimiter::hit($key, 60);

                    $run = Reconciliation::run('manual');
                    Audit::record('ledger.reconciliation_run', 'reconciliation_run', $run->id, ['mismatches' => $run->mismatches]);

                    Notification::make()
                        ->title($run->passed() ? __('Ledger and wallets match') : __('Differences found'))
                        ->{$run->passed() ? 'success' : 'danger'}()->send();
                }),
        ];
    }
}
