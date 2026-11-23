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
    use InteractsWithForms, WorldSlotTrait, WithFileUploads, EvergameTrait;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.advanced-page';

    protected static ?string $navigationLabel = 'Opções Avançada';

    protected static ?string $modelLabel = 'Opções Avançada';

    protected static ?string $title = 'Opções Avançada';

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
            ->title('Sucesso')
            ->body('Provedores Carregados com sucesso')
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
            ->title('Sucesso')
            ->body('Jogos Carregados com sucesso')
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
            ->title('Sucesso')
            ->body('Provedores Carregados com sucesso')
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
            ->title('Sucesso')
            ->body('Jogos Carregados com sucesso')
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
                Section::make('Updates')
                    ->description('Archive uploads are disabled for security. Deploy updates through your release pipeline.')
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
            ->title("Disabled")
            ->body("Uploading update archives is disabled. Deploy updates through your release pipeline.")
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
                ->title('Atenção')
                ->body('Você não pode realizar está alteração na versão demo')
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
            ->title('Sucesso')
            ->body('Migrações carregadas com sucesso')
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
                ->title('Atenção')
                ->body('Você não pode realizar está alteração na versão demo')
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
            ->title('Sucesso')
            ->body('Migrações carregadas com sucesso')
            ->success()
            ->send();
    }
}
