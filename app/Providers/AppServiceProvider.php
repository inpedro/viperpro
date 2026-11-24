<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Filament\Support\Assets\Js;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        FilamentAsset::register([
//            Js::make('filament-tools', base_path('vendor/sebastiaankloos/filament-code-editor/dist/filament-tools.js')),
//        ]);

        Schema::defaultStringLength(191);

        // Console (artisan, scheduler, queue workers) starts in the default tenant; commands that
        // must cover every tenant iterate with Tenancy::runAs(). HTTP requests use ResolveTenant.
        if ($this->app->runningInConsole() && ! \App\Core\Tenancy\Tenancy::isResolved()) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('tenants')) {
                    \App\Core\Tenancy\Tenancy::set(\App\Core\Tenancy\Models\Tenant::find(config('tenancy.default_tenant_id')));
                }
            } catch (\Throwable) {
                // no database yet
            }
        }

        // Admin uploads (logos, banners, ...) go under the current tenant's storage prefix.
        \Filament\Forms\Components\FileUpload::configureUsing(
            fn (\Filament\Forms\Components\FileUpload $upload) => $upload->directory(fn () => \App\Core\Tenancy\Tenancy::storagePrefix().'/uploads')
        );

        // Audit every model change made from a staff panel (attribute names only, never values).
        \Illuminate\Support\Facades\Event::listen(['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'], function (string $event, array $models) {
            if (! auth()->check() || \Filament\Facades\Filament::getCurrentPanel() === null) {
                return;
            }
            $model = $models[0] ?? null;
            $skip = [\App\Core\Ledger\Models\LedgerJournal::class, \App\Core\Ledger\Models\LedgerEntry::class, \App\Core\Ledger\Models\LedgerAccount::class,
                \App\Core\Security\AdminSession::class, \App\Core\Security\CallbackLogEntry::class, \App\Core\Audit\AuditLog::class];
            if (! $model instanceof \Illuminate\Database\Eloquent\Model || in_array($model::class, $skip, true)) {
                return;
            }
            $action = strtok(substr($event, strlen('eloquent.')), ':');
            $changed = array_values(array_diff(array_keys($action === 'updated' ? $model->getChanges() : $model->getAttributes()), ['updated_at', 'created_at']));
            \App\Core\Audit\Audit::record("admin.{$action}", class_basename($model), $model->getKey(), ['fields' => $changed]);
        });

        // Ledger safety net: journals any wallet balance change not made through WalletService.
        \App\Models\Wallet::observe(\App\Core\Wallet\WalletLedgerObserver::class);

        Builder::macro('whereLike', function ($attributes, string $searchTerm) {
            $this->where(function (Builder $query) use ($attributes, $searchTerm) {
                foreach (Arr::wrap($attributes) as $attribute) {
                    $query->when(
                        str_contains($attribute, '.'),
                        function (Builder $query) use ($attribute, $searchTerm) {
                            $buffer = explode('.', $attribute);
                            $attributeField = array_pop($buffer);
                            $relationPath = implode('.', $buffer);
                            $query->orWhereHas($relationPath, function (Builder $query) use ($attributeField, $searchTerm) {
                                $query->where($attributeField, 'LIKE', "%{$searchTerm}%");
                            });
                        },
                        function (Builder $query) use ($attribute, $searchTerm) {
                            $query->orWhere($attribute, 'LIKE', "%{$searchTerm}%");
                        }
                    );
                }
            });
            return $this;
        });
    }
}
