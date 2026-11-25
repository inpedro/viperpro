<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Pages;
use App\Models\Setting;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;


class Settings extends Page implements HasForms
{
    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public static function getModelLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Settings');
    }

    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.settings';




    protected static ?string $slug = 'configuracoes';

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public ?array $data = [];
    public Setting $setting;

    /**
     * @dev victormsalatiel - Meu instagram
     * @return void
     */
    public function mount(): void
    {
        $this->setting = Setting::first();
        $this->form->fill($this->setting->toArray());
    }

    /**
     * @dev victormsalatiel - Meu instagram
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Site details'))
                    ->schema([
                        TextInput::make('software_name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(191),
                        TextInput::make('software_description')
                            ->label(__('Description'))
                            ->maxLength(191),
                    ])->columns(2),

                Section::make('Logos')
                    ->schema([
                        FileUpload::make('software_favicon')
                            ->label(__('Favicon'))
                            ->placeholder(__('Upload a favicon'))
                            ->image(),
                        FileUpload::make('software_logo_white')
                            ->label(__('White logo'))
                            ->placeholder(__('Upload a white logo'))
                            ->image(),
                        FileUpload::make('software_logo_black')
                            ->label(__('Dark logo'))
                            ->placeholder(__('Upload a dark logo'))
                            ->image(),
                    ])->columns(3),

                Section::make(__('Background'))
                    ->schema([
                        FileUpload::make('software_background')
                            ->label(__('Background'))
                            ->placeholder(__('Upload a background'))
                            ->image()
                        ->columnSpanFull(),
                    ]),

                Section::make(__('Deposits and withdrawals'))
                    ->schema([
                        TextInput::make('min_deposit')
                            ->label(__('Min deposit'))
                            ->numeric()
                            ->maxLength(191),
                        TextInput::make('max_deposit')
                            ->label(__('Max deposit'))
                            ->numeric()
                            ->maxLength(191),
                        TextInput::make('min_withdrawal')
                            ->label(__('Min withdrawal'))
                            ->numeric()
                            ->maxLength(191),
                        TextInput::make('max_withdrawal')
                            ->label(__('Max withdrawal'))
                            ->numeric()
                            ->maxLength(191),
                        TextInput::make('rollover')
                            ->label(__('Rollover'))
                            ->numeric()
                            ->maxLength(191),
                    ])->columns(5),

                Section::make(__('Football'))
                    ->description(__('Football settings'))
                    ->schema([
                        TextInput::make('soccer_percentage')
                            ->label(__('Football commission (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->maxLength(191),

                        Toggle::make('turn_on_football')
                            ->inline(false)
                            ->label(__('Enable football')),
                    ])->columns(2),

                Section::make(__('Fees'))
                    ->description(__('Platform revenue settings'))
                    ->schema([
                        TextInput::make('revshare_percentage')
                            ->label(__('Revenue share (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->maxLength(191),
                        Toggle::make('revshare_reverse')
                            ->inline(false)
                            ->label(__('Enable negative revenue share'))
                            ->helperText(__('When enabled, players\' winnings are deducted from their affiliate\'s commission and the balance can go negative.'))
                        ,
                        TextInput::make('ngr_percent')
                            ->label('NGR (%)')
                            ->numeric()
                            ->suffix('%')
                            ->maxLength(191),
                    ])->columns(3),
                Section::make(__('General information'))
                    ->schema([
                        TextInput::make('initial_bonus')
                            ->label(__('Initial bonus (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->maxLength(191),
                        TextInput::make('currency_code')
                            ->label(__('Currency'))
                            ->maxLength(191),
                        Select::make('decimal_format')->options([
                            'dot' => __('Dot'),
                        ]),
                        Select::make('currency_position')->options([
                            'left' => __('Left'),
                            'right' => __('Right'),
                        ]),
                        Toggle::make('disable_spin')
                            ->label(__('Disable spin'))
                        ,
                        Toggle::make('suitpay_is_enable')
                            ->label(__('SuitPay enabled'))
                        ,
                        Toggle::make('stripe_is_enable')
                            ->label(__('Stripe enabled'))
                        ,
                    ])->columns(4),
            ])
            ->statePath('data');
    }

    /**
     * @dev victormsalatiel - Meu instagram
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    /**
     *
     * @dev victormsalatiel - Meu instagram
     * @return array
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Submit'))
                ->action(fn () => $this->submit())
                ->submit('submit')
            //->url(route('filament.admin.pages.dashboard'))
            ,
        ];
    }

    /**
     * @dev victormsalatiel - Meu instagram
     * @param $array
     * @return mixed|void
     */
    private function uploadFile($array)
    {
        if(!empty($array) && is_array($array) || !empty($array) && is_object($array)) {
            foreach ($array as $k => $temporaryFile) {
                if ($temporaryFile instanceof TemporaryUploadedFile) {
                    $path = \Helper::upload($temporaryFile);
                    if($path) {
                        return $path['path'];
                    }
                }else{
                    return $temporaryFile;
                }
            }
        }
    }


    /**
     * @dev victormsalatiel - Meu instagram
     * @return void
     */
    public function submit(): void
    {
        try {
            if(env('APP_DEMO')) {
                Notification::make()
                    ->title(__('Attention'))
                    ->body(__('You cannot make this change in the demo version'))
                    ->danger()
                    ->send();
                return;
            }


            $setting = Setting::first();

            if(!empty($setting)) {

                $favicon   = $this->data['software_favicon'];
                $logoWhite = $this->data['software_logo_white'];
                $logoBlack = $this->data['software_logo_black'];
                $softwareBackground = $this->data['software_background'];

                if (is_array($softwareBackground) || is_object($softwareBackground)) {
                    if(!empty($softwareBackground)) {
                        $this->data['software_background'] = $this->uploadFile($softwareBackground);
                    }
                }

                if (is_array($favicon) || is_object($favicon)) {
                    if(!empty($favicon)) {
                        $this->data['software_favicon'] = $this->uploadFile($favicon);
                    }
                }

                if (is_array($logoWhite) || is_object($logoWhite)) {
                    if(!empty($logoWhite)) {
                        $this->data['software_logo_white'] = $this->uploadFile($logoWhite);
                    }
                }

                if (is_array($logoBlack) || is_object($logoBlack)) {
                    if(!empty($logoBlack)) {
                        $this->data['software_logo_black'] = $this->uploadFile($logoBlack);
                    }
                }



                if($setting->update($this->data)) {

                    Cache::put(\App\Core\Tenancy\Tenancy::key('setting'), $setting);

                    Notification::make()
                        ->title(__('Data updated'))
                        ->body(__('Data updated successfully.'))
                        ->success()
                        ->send();

                    redirect(route('filament.admin.pages.dashboard-admin'));

                }
            }


        } catch (Halt $exception) {
            Notification::make()
                ->title(__('Could not update the data.'))
                ->body(__('Could not update the data.'))
                ->danger()
                ->send();
        }
    }


}
