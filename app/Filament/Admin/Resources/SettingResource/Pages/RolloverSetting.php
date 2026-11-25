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

class RolloverSetting extends Page implements HasForms
{
    use HasPageSidebar, InteractsWithForms;

    protected static string $resource = SettingResource::class;

    protected static string $view = 'filament.resources.setting-resource.pages.rollover-setting';

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
        return __('Rollover');
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

                redirect(route('filament.admin.resources.settings.rollover', ['record' => $this->record->id]));

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
                Section::make(__('Bonus settings'))
                    ->description(__('Platform bonus'))
                    ->schema([
                        TextInput::make('rollover_deposit')
                            ->label(__('Deposit rollover'))
                            ->numeric()
                            ->default(1)
                            ->suffix('x')
                            ->helperText(__('Deposit wagering multiplier'))
                            ->maxLength(191),
                        Group::make()->schema([
                            TextInput::make('rollover')
                                ->label(__('Bonus rollover'))
                                ->numeric()
                                ->default(1)
                                ->suffix('x')
                                ->helperText(__('Bonus wagering multiplier'))
                                ->maxLength(191),
                            TextInput::make('rollover_protection')
                                ->label(__('Bonus rollover protection'))
                                ->numeric()
                                ->default(1)
                                ->suffix('x')
                                ->helperText(__('Minimum number of transactions to clear the rollover'))
                                ->maxLength(191),
                        ])->columns(2),
                        Toggle::make('disable_rollover')
                            ->label(__('Disable rollover'))
                            ->helperText(__('When unchecked, bonus and deposit rollover are active'))
                    ])->columns(2)
            ])
            ->statePath('data') ;
    }
}
