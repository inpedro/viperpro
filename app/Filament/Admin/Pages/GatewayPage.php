<?php

namespace App\Filament\Admin\Pages;

use App\Models\Gateway;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;

class GatewayPage extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.gateway-page';

    public ?array $data = [];
    public Gateway $setting;

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
        $gateway = Gateway::first();
        if(!empty($gateway)) {
            $this->setting = $gateway;
            $this->form->fill($this->setting->toArray());
        }else{
            $this->form->fill();
        }
    }

    /**
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('DigitoPay')
                    ->description(__('DigitoPay credentials'))
                    ->schema([
                        TextInput::make('digitopay_uri')
                            ->label('Client URI')
                            ->placeholder(__('API URL'))
                            ->helperText(__('Official DigitoPay API base URL (HTTPS). Its host must also be listed in DIGITOPAY_ALLOWED_HOSTS.'))
                            ->maxLength(191),
                        TextInput::make('digitopay_cliente_id')
                            ->label('Client ID')
                            ->placeholder(__('Client ID'))
                            ->maxLength(191),
                        TextInput::make('digitopay_cliente_secret')
                            ->label('Client Secret')
                            ->placeholder(__('Client secret'))
                            ->maxLength(191),
                    ])->columns(3),
                Section::make('Mercado Pago')
                    ->description(__('Mercado Pago API keys'))
                    ->schema([
                        TextInput::make('mp_client_id')
                            ->label('Mercado Pago Client ID')
                            ->placeholder(__('Enter the Mercado Pago client ID'))
                            ->maxLength(191),
                        TextInput::make('mp_client_secret')
                            ->label(__('Mercado Pago client secret'))
                            ->placeholder(__('Enter the Mercado Pago client secret'))
                            ->maxLength(191),
                        TextInput::make('mp_public_key')
                            ->label('Mercado Pago Public Key')
                            ->placeholder(__('Enter the Mercado Pago public key'))
                            ->maxLength(191),
                        TextInput::make('mp_access_token')
                            ->label('Mercado Pago Access Token')
                            ->placeholder(__('Enter the Mercado Pago access token'))
                            ->maxLength(191),
                    ])->columns(2),
                Section::make('SharkPay')
                    ->description(__('SharkPay keys'))
                    ->schema([
                        TextInput::make('public_key')
                            ->label(__('Public key'))
                            ->placeholder(__('Enter the public key'))
                            ->maxLength(191),
                        TextInput::make('private_key')
                            ->label(__('Private key'))
                            ->placeholder(__('Enter the private key'))
                            ->maxLength(191),
                    ])->columns(2),
                Section::make('Stripe')
                    ->description(__('Stripe credentials'))
                    ->schema([
                        TextInput::make('stripe_public_key')
                            ->label(__('Public key'))
                            ->placeholder(__('Enter the public key'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('stripe_secret_key')
                            ->label(__('Private key'))
                            ->placeholder(__('Enter the private key'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('stripe_webhook_key')
                            ->label(__('Webhook key'))
                            ->placeholder(__('Enter the webhook key'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ]),

                Section::make('Suitpay')
                    ->description(__('SuitPay credentials'))
                    ->schema([
                        TextInput::make('suitpay_uri')
                            ->label('Client URI')
                            ->placeholder(__('API URL'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('suitpay_cliente_id')
                            ->label('Client ID')
                            ->placeholder(__('Client ID'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('suitpay_cliente_secret')
                            ->label('Client Secret')
                            ->placeholder(__('Client secret'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }


    /**
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

            $setting = Gateway::first();
            if(!empty($setting)) {
                if($setting->update($this->data)) {

                    Notification::make()
                        ->title(__('Keys updated'))
                        ->body(__('Your keys were updated successfully.'))
                        ->success()
                        ->send();
                }
            }else{
                if(Gateway::create($this->data)) {
                    Notification::make()
                        ->title(__('Keys created'))
                        ->body(__('Your keys were created successfully.'))
                        ->success()
                        ->send();
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
