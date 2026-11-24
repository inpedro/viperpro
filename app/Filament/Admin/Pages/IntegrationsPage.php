<?php

namespace App\Filament\Admin\Pages;

use App\Core\Games\GameProviders;
use App\Core\Payments\PaymentGateways;
use App\Core\Security\CallbackLogEntry;
use App\Core\Tenancy\Tenancy;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Integration health + webhook monitor for this casino (Phase 7). */
class IntegrationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $slug = 'integrations';

    protected static string $view = 'filament.pages.integrations-page';

    public static function getNavigationLabel(): string
    {
        return __('Integrations');
    }

    public function getTitle(): string
    {
        return __('Integrations');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin') && Tenancy::id() !== null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getHealth(): array
    {
        $rows = [];
        foreach (PaymentGateways::all() as $g) {
            $h = $g->getHealthStatus();
            $rows[] = ['type' => __('Payments'), 'name' => $g->getName(), 'status' => $h->status, 'notes' => implode(' ', $h->messages),
                'capabilities' => implode(', ', $g->getCapabilities()), 'payouts' => implode(', ', $g->getPayoutMethods())];
        }
        foreach (GameProviders::all() as $p) {
            $h = $p->getHealthStatus();
            $rows[] = ['type' => __('Games'), 'name' => $p->getName(), 'status' => $h->status, 'notes' => implode(' ', $h->messages),
                'capabilities' => implode(', ', $p->getCapabilities()), 'payouts' => ''];
        }

        return $rows;
    }

    /** Last 24h counters per outcome. */
    public function getStats(): array
    {
        return CallbackLogEntry::query()->where('created_at', '>=', now()->subDay())
            ->selectRaw('outcome, COUNT(*) AS c')->groupBy('outcome')->pluck('c', 'outcome')->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CallbackLogEntry::query())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label(__('Date'))->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('source')->searchable(),
                Tables\Columns\TextColumn::make('outcome')->badge()->color(fn (string $state) => match ($state) {
                    'accepted' => 'success', 'ignored' => 'gray', 'rejected' => 'danger', default => 'warning',
                }),
                Tables\Columns\TextColumn::make('reason')->wrap()->limit(80),
                Tables\Columns\TextColumn::make('reference')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('ip'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('outcome')->options(['accepted' => 'accepted', 'ignored' => 'ignored', 'rejected' => 'rejected', 'error' => 'error']),
                Tables\Filters\SelectFilter::make('channel')->options(['payment' => 'payment', 'provider' => 'provider']),
            ])
            ->actions([
                Tables\Actions\Action::make('payload')->label(__('Details'))->icon('heroicon-o-eye')
                    ->modalHeading(fn (CallbackLogEntry $r) => "{$r->source} · {$r->outcome}")
                    ->modalContent(fn (CallbackLogEntry $r) => view('filament.pages.partials.callback-payload', ['entry' => $r]))
                    ->modalSubmitAction(false),
            ])
            ->poll('30s');
    }
}
