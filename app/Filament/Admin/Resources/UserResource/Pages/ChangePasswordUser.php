<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use AymanAlhattami\FilamentPageWithSidebar\Traits\HasPageSidebar;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;

class ChangePasswordUser extends Page implements HasForms
{
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Change password');
    }

    use HasPageSidebar, InteractsWithForms;

    public User $record;
    public ?array $data = [];


    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.change-password-user';


    /**
     * @return void
     */
    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * @param Form $form
     * @return Form
     */
//    public function form(Form $form): Form
//    {
//        return $form
//            ->schema($this->getFormSchema())
//            ->statePath('data');
//    }

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

            $user = User::find($this->record->id);

            $user->update(['password' => $this->data['password']]);
            Notification::make()
                ->title(__('Password changed'))
                ->body(__('The password was changed successfully.'))
                ->success()
                ->send();
        } catch (Halt $exception) {
            return;
        }
    }

    /**
     * @return array|\Filament\Forms\Components\Component[]
     */
    public function getFormSchema(): array
    {
        return [
            Section::make(__('Change your password'))
                ->description(__('Set a new password'))
                ->schema([
                   TextInput::make('password')
                        ->label(__('Password'))
                        ->placeholder(__('Enter the password'))
                        ->password()
                        ->required()
                        ->maxLength(191),
                    TextInput::make('confirm_password')
                        ->label(__('Confirm password'))
                        ->placeholder(__('Confirm your password'))
                        ->password()
                        ->confirmed()
                        ->maxLength(191),
                ])
                ->columns(2)
                ->statePath('data')

        ];
    }
}
