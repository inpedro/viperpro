<?php

namespace App\Filament\Admin\Pages;

use App\Models\SettingMail;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;

class SettingMailPage extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.setting-mail-page';

    public ?array $data = [];
    public SettingMail $setting;

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
        $smtp = SettingMail::first();
        if(!empty($smtp)) {
            $this->setting = $smtp;
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
                Section::make('SMTP')
                    ->description(__('SMTP credentials'))
                    ->schema([
                        TextInput::make('software_smtp_type')
                            ->label(__('Mailer'))
                            ->placeholder(__('Mailer (smtp)'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_host')
                            ->label(__('Host'))
                            ->placeholder(__('Mail host'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_port')
                            ->label(__('Port'))
                            ->placeholder(__('Port'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_username')
                            ->label(__('User'))
                            ->placeholder(__('Username'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_password')
                            ->label(__('Password'))
                            ->placeholder(__('Enter the password'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_encryption')
                            ->label(__('Encryption'))
                            ->placeholder(__('Encryption (tls or ssl)'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_from_address')
                            ->label(__('Sender e-mail'))
                            ->placeholder(__('Sender e-mail address'))
                            ->maxLength(191),
                        TextInput::make('software_smtp_mail_from_name')
                            ->label(__('Sender name'))
                            ->placeholder(__('Sender name'))
                            ->maxLength(191)
                    ])->columns(4),
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

            $setting = SettingMail::first();
            if(!empty($setting)) {

                if($setting->update($this->data)) {
                    Notification::make()
                        ->title(__('Keys updated'))
                        ->body(__('Your keys were updated successfully.'))
                        ->success()
                        ->send();
                }
            }else{
                if(SettingMail::create($this->data)) {
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
