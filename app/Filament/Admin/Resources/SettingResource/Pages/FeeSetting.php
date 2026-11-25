<?php

namespace App\Filament\Admin\Resources\SettingResource\Pages;

use App\Filament\Admin\Resources\SettingResource;
use App\Models\Setting;
use AymanAlhattami\FilamentPageWithSidebar\Traits\HasPageSidebar;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeeSetting extends Page implements HasForms
{
    use HasPageSidebar, InteractsWithForms;

    protected static string $resource = SettingResource::class;

    protected static string $view = 'filament.resources.setting-resource.pages.fee-setting';

    /**
     * @dev @victormsalatiel
     * @param Model $record
     * @return bool
     */
    public static function canView(Model $record): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @return string|Htmlable
     */
    public function getTitle(): string | Htmlable
    {
        return __('Fees');
    }

    public Setting $record;
    public ?array $data = [];

    /**
     * @dev victormsalatiel - Meu instagram
     * @return void
     */
    public function mount(): void
    {
        $setting = Setting::first();
        $this->record = $setting;
        $this->form->fill($setting->toArray());
    }

    /**
     * @dev victormsalatiel - Meu instagram
     * @return void
     */
    public function save()
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

            $setting = Setting::find($this->record->id);

            if($setting->update($this->data)) {
                Cache::put(\App\Core\Tenancy\Tenancy::key('setting'), $setting);

                Notification::make()
                    ->title(__('Data updated'))
                    ->body(__('Data updated successfully.'))
                    ->success()
                    ->send();

                redirect(route('filament.admin.resources.settings.fee', ['record' => $this->record->id]));

            }
        } catch (Halt $exception) {
            return;
        }
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
                Section::make(__('Fee settings'))
                    ->description(__('Platform fees'))
                    ->schema([
                        Grid::make()->schema([
                            TextInput::make('revshare_percentage')
                                ->label(__('Revenue share (%)'))
                                ->numeric()
                                ->suffix('%')
                                ->helperText(__('Default revenue share for every user who becomes an affiliate.'))
                                ->maxLength(191),
                            TextInput::make('cpa_baseline')
                                ->label('CPA Baseline')
                                ->numeric()
                                ->prefix(fn () => \App\Core\Localization\Formatter::tenantCurrencySymbol())
                                ->maxLength(191),
                            TextInput::make('cpa_value')
                                ->label(__('CPA amount'))
                                ->numeric()
                                ->prefix(fn () => \App\Core\Localization\Formatter::tenantCurrencySymbol())
                                ->maxLength(191),
                            TextInput::make('ngr_percent')
                                ->helperText(__('This rate is deducted from the affiliate\'s earnings for the platform.'))
                                ->label('NGR (%)')
                                ->numeric()
                                ->suffix('%')
                                ->maxLength(191),
                        ])->columns(2),
                        Toggle::make('revshare_reverse')
                            ->inline(true)
                            ->label(__('Enable negative revenue share'))
                            ->helperText(__('When enabled, players\' winnings are deducted from their affiliate\'s commission and the balance can go negative.'))
                        ,
                    ])
            ])
            ->statePath('data') ;
    }
}
