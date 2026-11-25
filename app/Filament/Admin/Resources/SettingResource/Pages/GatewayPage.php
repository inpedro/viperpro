<?php

namespace App\Filament\Admin\Resources\SettingResource\Pages;

use App\Filament\Admin\Resources\SettingResource;
use App\Models\Setting;
use AymanAlhattami\FilamentPageWithSidebar\Traits\HasPageSidebar;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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

class GatewayPage extends Page implements HasForms
{
    use HasPageSidebar, InteractsWithForms;

    protected static string $resource = SettingResource::class;

    protected static string $view = 'filament.resources.setting-resource.pages.gateway-page';

    /**
     * @return string|Htmlable
     */
    public function getTitle(): string | Htmlable
    {
        return 'Gateways';
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
                Section::make(__('Gateways'))
                    ->description(__('Enable or disable your payment gateways'))
                    ->schema([
                        Select::make('default_gateway')
                            ->label(__('Default withdrawal gateway'))
                            ->options([
                                'digitopay' => 'Digitopay',
                                'sharkpay' => 'Sharkpay',
                                'suitpay' => 'Suitpay',
                            ])->columnSpanFull()
                        ,
                        Toggle::make('digitopay_is_enable')
                            ->label('DigitoPay'),
                        Toggle::make('sharkpay_is_enable')
                            ->label(__('SharkPay enabled')),
                        Toggle::make('suitpay_is_enable')
                            ->label(__('SuitPay enabled')),
                        Toggle::make('stripe_is_enable')
                            ->label(__('Stripe enabled')),
                        Toggle::make('mercadopago_is_enable')
                            ->label(__('Mercado Pago enabled')),
//                        Toggle::make('bspay_is_enable')
//                            ->label(__('BSPay enabled')),
                    ])->columns(2)
            ])
            ->statePath('data') ;
    }
}
