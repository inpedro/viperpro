<?php

namespace App\Filament\Admin\Pages;

use App\Traits\Providers\EvergameTrait;
use App\Traits\Providers\WorldSlotTrait;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Artisan;
use Livewire\WithFileUploads;

class AdvancedPage extends Page implements HasForms
{
    public static function getNavigationLabel(): string
    {
        return __('Advanced options');
    }

    public static function getModelLabel(): string
    {
        return __('Advanced options');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Advanced options');
    }

    use InteractsWithForms, WorldSlotTrait, WithFileUploads, EvergameTrait;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.advanced-page';




    protected static ?string $slug = 'advanced-options';

    public ?array $data = [];
    public $output;

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @return void
     */
    public function mount(): void
    {

    }

    /**
     * @param $type
     * @return void
     */
    public function loadProvider($type)
    {
        self::getProviderWorldslot($type);
        Notification::make()
            ->title(__('Success'))
            ->body(__('Providers loaded successfully'))
            ->success()
            ->send();
    }


    /**
     * @return void
     */
    public function loadGames()
    {
        self::getGamesWorldslot();
        Notification::make()
            ->title(__('Success'))
            ->body(__('Games loaded successfully'))
            ->success()
            ->send();
    }




    /**
     * @return void
     */

    public function loadProviderEvergame()
    {
        self::getProviderEvergame();
        Notification::make()
            ->title(__('Success'))
            ->body(__('Providers loaded successfully'))
            ->success()
            ->send();
    }


    /**
     * @return void
     */
    public function loadGamesEvergame()
    {
        self::getGamesEvergame();
        Notification::make()
            ->title(__('Success'))
            ->body(__('Games loaded successfully'))
            ->success()
            ->send();
    }

    /**
     * @return void
     */
    public function upload()
    {

    }

    /**
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {

        return $data;
    }

    /**
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Updates'))
                    ->description(__('Archive uploads are disabled for security. Deploy updates through your release pipeline.'))
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('update_disabled')
                            ->content('Uploading ZIP archives that are extracted over the application is no longer supported.'),
                    ])

            ])
            ->statePath('data');
    }

    /**
     * @return void
     */
    public function submit(): void
    {
        // DISABLED (security): this extracted any uploaded ZIP over base_path(), i.e. arbitrary code
        // execution for anyone holding an admin session. Updates must be deployed through the normal
        // release pipeline (git + composer + migrations). A signed, verified addon installer is planned
        // in Phase 3 (see docs/REFACTORING_PLAN.md).
        Notification::make()
            ->title(__('Disabled'))
            ->body(__('Uploading update archives is disabled. Deploy updates through your release pipeline.'))
            ->danger()
            ->send();
    }

    /**
     * @return void
     */
    public function runMigrate()
    {
        if(env('APP_DEMO')) {
            Notification::make()
                ->title(__('Attention'))
                ->body(__('You cannot make this change in the demo version'))
                ->danger()
                ->send();
            return;
        }

        // Executar o comando Artisan para rodar as migrations
        Artisan::call('migrate');

        // Você também pode adicionar a opção '--seed' para rodar os seeders, se necessário
        // Artisan::call('migrate --seed');

        // Obter a saída do comando, se necessário
        $this->output = Artisan::output();
        Notification::make()
            ->title(__('Success'))
            ->body(__('Migrations loaded successfully'))
            ->success()
            ->send();
    }

    /**
     * @return void
     */
    public function runMigrateWithSeed()
    {
        if(env('APP_DEMO')) {
            Notification::make()
                ->title(__('Attention'))
                ->body(__('You cannot make this change in the demo version'))
                ->danger()
                ->send();
            return;
        }

        // Executar o comando Artisan para rodar as migrations
        Artisan::call('migrate --seed');

        // Você também pode adicionar a opção '--seed' para rodar os seeders, se necessário
        // Artisan::call('migrate --seed');

        // Obter a saída do comando, se necessário
        $this->output = Artisan::output();
        Notification::make()
            ->title(__('Success'))
            ->body(__('Migrations loaded successfully'))
            ->success()
            ->send();
    }
}
