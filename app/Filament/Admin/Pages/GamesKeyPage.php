<?php

namespace App\Filament\Admin\Pages;

use App\Models\GamesKey;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;

class GamesKeyPage extends Page implements HasForms
{
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Game provider keys');
    }

    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.games-key-page';


    // protected static ?string $slug = 'chaves-dos-jogos';

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }


    public ?array $data = [];
    public ?GamesKey $setting;

    /**
     * @return void
     */
    public function mount(): void
    {
        $gamesKey = GamesKey::first();
        if(!empty($gamesKey)) {
            $this->setting = $gamesKey;
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
                Section::make('Shark Connect API')
                    ->description(__('Shark Connect credentials'))
                    ->schema([
                        TextInput::make('venix_agent_code')
                            ->label('Agent Code')
                            ->placeholder(__('Agent code'))
                            ->maxLength(191),
                        TextInput::make('venix_agent_token')
                            ->label('Agent Token')
                            ->placeholder(__('Agent token'))
                            ->maxLength(191),
                        TextInput::make('venix_agent_secret')
                            ->label('Agent Secret')
                            ->placeholder(__('Agent secret'))
                            ->maxLength(191),
                    ])
                    ->columns(3),
                Section::make('PlayIGaming Aggregator API')
                    ->description(__('PlayIGaming Aggregator credentials'))
                    ->schema([
                        TextInput::make('pig_agent_code')
                            ->label('Agent Code')
                            ->placeholder(__('Agent code'))
                            ->maxLength(191),
                        TextInput::make('pig_agent_token')
                            ->label('Agent Token')
                            ->placeholder(__('Agent token'))
                            ->maxLength(191),
                        TextInput::make('pig_agent_secret')
                            ->label('Agent Secret')
                            ->placeholder(__('Agent secret'))
                            ->maxLength(191),
                    ])
                    ->columns(3),
                Section::make('Play iGaming API')
                    ->description(__('Contact the provider through its official channels'))
                    ->schema([
                        TextInput::make('play_gaming_hall')
                            ->label(__('Hall'))
                            ->placeholder(__('Hall'))
                            ->maxLength(191),
                        TextInput::make('play_gaming_key')
                            ->label(__('Key'))
                            ->placeholder(__('Key'))
                            ->maxLength(191),
                        TextInput::make('play_gaming_login')
                            ->label(__('Login'))
                            ->placeholder(__('Login'))
                            ->maxLength(191),
                    ])
                    ->columns(3),

                Section::make('EverGame API')
                    ->description(__('EverGame credentials'))
                    ->schema([
                        TextInput::make('evergame_agent_code')
                            ->label('Agent Code')
                            ->placeholder(__('Agent code'))
                            ->maxLength(191),
                        TextInput::make('evergame_agent_token')
                            ->label('Agent Token')
                            ->placeholder(__('Agent token'))
                            ->maxLength(191),
                        TextInput::make('evergame_api_endpoint')
                            ->label('Api Endpoint')
                            ->placeholder(__('API endpoint'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Games2Api')
                    ->description(__('Games2Api credentials'))
                    ->schema([
                        TextInput::make('games2_agent_code')
                            ->label('Agent Code')
                            ->placeholder(__('Agent code'))
                            ->maxLength(191),
                        TextInput::make('games2_agent_token')
                            ->label('Agent Token')
                            ->placeholder(__('Agent token'))
                            ->maxLength(191),
                        TextInput::make('games2_agent_secret_key')
                            ->label('Agent Secret Key')
                            ->placeholder(__('Agent secret key'))
                            ->maxLength(191),
                        TextInput::make('games2_api_endpoint')
                            ->label('Api Endpoint')
                            ->placeholder(__('API endpoint'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('World Slot API')
                    ->description(__('World Slot credentials'))
                    ->schema([
                        TextInput::make('worldslot_agent_code')
                            ->label('Agent Code')
                            ->placeholder(__('Agent code'))
                            ->maxLength(191),
                        TextInput::make('worldslot_agent_token')
                            ->label('Agent Token')
                            ->placeholder(__('Agent token'))
                            ->maxLength(191),
                        TextInput::make('worldslot_agent_secret_key')
                            ->label('Agent Secret Key')
                            ->placeholder(__('Agent secret key'))
                            ->maxLength(191),
                        TextInput::make('worldslot_api_endpoint')
                            ->label('Api Endpoint')
                            ->placeholder(__('API endpoint'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Slotegrator API')
                    ->description(__('Slotegrator credentials'))
                    ->schema([
                        TextInput::make('merchant_url')
                            ->label('Merchant URL')
                            ->placeholder(__('API URL'))
                            ->maxLength(191),
                        TextInput::make('merchant_id')
                            ->label('Merchant ID')
                            ->placeholder(__('Merchant ID'))
                            ->maxLength(191),
                        TextInput::make('merchant_key')
                            ->placeholder(__('Merchant key'))
                            ->label('Merchant Key')
                            ->maxLength(191),
                    ])
                    ->columns(3),



                Section::make('Salsa API')
                    ->description(__('Salsa credentials'))
                    ->schema([
                        TextInput::make('salsa_base_uri')
                            ->label('Salsa URI')
                            ->placeholder(__('Salsa base URL'))
                            ->maxLength(191),
                        TextInput::make('salsa_pn')
                            ->label('Salsa PN')
                            ->placeholder(__('PN'))
                            ->maxLength(191),
                        TextInput::make('salsa_key')
                            ->label('Salsa Key')
                            ->placeholder(__('Salsa key'))
                            ->maxLength(191),
                    ])
                    ->columns(3),

                Section::make('Vibra Gaming API')
                    ->description(__('Vibra Gaming credentials'))
                    ->schema([
                        TextInput::make('vibra_site_id')
                            ->label('Vibra Site ID')
                            ->placeholder(__('Vibra site ID'))
                            ->maxLength(191),
                        TextInput::make('vibra_game_mode')
                            ->label('Vibra Game Mode')
                            ->placeholder(__('Vibra game mode'))
                            ->maxLength(191),
                    ])
                    ->columns(2),

                Section::make('Fivers API')
                    ->description(__('Fivers credentials'))
                    ->schema([
                        TextInput::make('agent_code')
                            ->label('Agent Code')
                            ->placeholder(__('Agent code'))
                            ->maxLength(191),
                        TextInput::make('agent_token')
                            ->label('Agent Token')
                            ->placeholder(__('Agent token'))
                            ->maxLength(191),
                        TextInput::make('agent_secret_key')
                            ->label('Agent Secret Key')
                            ->placeholder(__('Agent secret key'))
                            ->maxLength(191),
                        TextInput::make('api_endpoint')
                            ->label('Api Endpoint')
                            ->placeholder(__('API endpoint'))
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

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

            $setting = GamesKey::first();
            if(!empty($setting)) {
                if($setting->update($this->data)) {
                    Notification::make()
                        ->title(__('Keys updated'))
                        ->body(__('Your keys were updated successfully.'))
                        ->success()
                        ->send();
                }
            }else{
                if(GamesKey::create($this->data)) {
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
