<?php

namespace App\Filament\Admin\Resources\SettingResource\Pages;

use App\Filament\Admin\Resources\SettingResource;
use App\Models\Setting;
use AymanAlhattami\FilamentPageWithSidebar\Traits\HasPageSidebar;
use Filament\Forms\Components\Group;
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

class PaymentSetting extends Page implements HasForms
{
    use HasPageSidebar, InteractsWithForms;

    protected static string $resource = SettingResource::class;

    protected static string $view = 'filament.resources.setting-resource.pages.payment-setting';

    /**
     * @return string|Htmlable
     */
    public function getTitle(): string | Htmlable
    {
        return __('Payments');
    }

    public Setting $record;
    public ?array $data = [];

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

                redirect(route('filament.admin.resources.settings.payment', ['record' => $this->record->id]));

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
                        TextInput::make('initial_bonus')
                            ->label(__('Initial bonus (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->maxLength(191),
                        TextInput::make('currency_code')
                            ->label(__('Currency'))
                            ->maxLength(191),
//                        Select::make('decimal_format')->options([
//                            'dot' => __('Dot'),
//                        ]),
//                        Select::make('currency_position')->options([
//                            'left' => __('Left'),
//                            'right' => __('Right'),
//                        ]),

                        Group::make()
                            ->label(__('Sub-affiliate percentages'))
                            ->schema([
                            TextInput::make('perc_sub_lv1')
                                ->label(__('Sub-affiliate level 1 (%)'))
                                ->numeric()
                                ->maxLength(191),
                            TextInput::make('perc_sub_lv2')
                                ->label(__('Sub-affiliate level 2 (%)'))
                                ->numeric()
                                ->maxLength(191),
                            TextInput::make('perc_sub_lv3')
                                ->label(__('Sub-affiliate level 3 (%)'))
                                ->numeric()
                                ->maxLength(191),
                        ])->columnSpanFull()->columns(3),
                        Toggle::make('disable_spin')
                            ->label(__('Disable spin'))
                        ,
                    ])->columns(2)
            ])
            ->statePath('data') ;
    }
}
