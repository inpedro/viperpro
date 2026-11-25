<?php

namespace App\Filament\Admin\Pages;

use App\Models\CustomLayout;
use Creagia\FilamentCodeField\CodeField;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;

class LayoutCssCustom extends Page implements HasForms
{
    public static function getNavigationLabel(): string
    {
        return __('Layout customisation');
    }

    public static function getModelLabel(): string
    {
        return __('Layout customisation');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Layout customisation');
    }

    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.layout-css-custom';




    protected static ?string $slug = 'custom-layout';

    public ?array $data = [];
    public CustomLayout $custom;

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
        $this->custom = CustomLayout::current();
        $this->form->fill($this->custom->toArray());
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
                Section::make()
                    ->label(__('Background'))
                    ->schema([
                        ColorPicker::make('background_base')
                            ->label(__('Main background'))
                            ->required(),
                        ColorPicker::make('background_base_dark')
                            ->label(__('Main background (dark)'))
                            ->required(),
                        ColorPicker::make('carousel_banners')
                            ->label(__('Carousel banners'))
                            ->required(),
                        ColorPicker::make('carousel_banners_dark')
                            ->label(__('Carousel banners (dark)'))
                            ->required(),
                    ])->columns(4)
                ,
                Section::make(__('Sidebar, top bar and footer'))
                    ->description(__('Customise the look of your site.'))
                    ->collapsible()
                    ->collapsed(true)
                    ->schema([
                        ColorPicker::make('sidebar_color')
                            ->label(__('Sidebar'))
                            ->required(),

                        ColorPicker::make('sidebar_color_dark')
                            ->label(__('Sidebar (dark)'))
                            ->required(),

                        ColorPicker::make('navtop_color')
                            ->label(__('Top bar'))
                            ->required(),

                        ColorPicker::make('navtop_color_dark')
                            ->label(__('Top bar (dark)'))
                            ->required(),

                        ColorPicker::make('side_menu')
                            ->label(__('Side menu box'))
                            ->required(),

                        ColorPicker::make('side_menu_dark')
                            ->label(__('Side menu box (dark)'))
                            ->required(),

                        ColorPicker::make('footer_color')
                            ->label(__('Footer colour'))
                            ->required(),

                        ColorPicker::make('footer_color_dark')
                            ->label(__('Footer colour (dark)'))
                            ->required(),
                    ])->columns(4)
                ,
                Section::make(__('Customisation'))
                    ->description(__('Customise the look of your site.'))
                    ->collapsible()
                    ->collapsed(true)
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label(__('Primary colour'))
                            ->required(),
                        ColorPicker::make('primary_opacity_color')
                            ->label(__('Primary colour (translucent)'))
                            ->required(),

                        ColorPicker::make('input_primary')
                            ->label(__('Input primary'))
                            ->required(),
                        ColorPicker::make('input_primary_dark')
                            ->label(__('Input primary (dark)'))
                            ->required(),

                        ColorPicker::make('card_color')
                            ->label(__('Card primary'))
                            ->required(),
                        ColorPicker::make('card_color_dark')
                            ->label(__('Card primary (dark)'))
                            ->required(),

                        ColorPicker::make('secundary_color')
                            ->label(__('Secondary colour'))
                            ->required(),
                        ColorPicker::make('gray_dark_color')
                            ->label(__('Dark grey'))
                            ->required(),
                        ColorPicker::make('gray_light_color')
                            ->label(__('Light grey'))
                            ->required(),
                        ColorPicker::make('gray_medium_color')
                            ->label(__('Medium grey'))
                            ->required(),
                        ColorPicker::make('gray_over_color')
                            ->label(__('Hover grey'))
                            ->required(),
                        ColorPicker::make('title_color')
                            ->label(__('Title colour'))
                            ->required(),
                        ColorPicker::make('text_color')
                            ->label(__('Text colour'))
                            ->required(),
                        ColorPicker::make('sub_text_color')
                            ->label(__('Secondary text colour'))
                            ->required(),
                        ColorPicker::make('placeholder_color')
                            ->label(__('Placeholder colour'))
                            ->required(),
                        ColorPicker::make('background_color')
                            ->label(__('Background colour'))
                            ->required(),
                        TextInput::make('border_radius')
                            ->label(__('Border radius'))
                            ->required(),
                    ])->columns(4)
                ,
                Section::make(__('Base HTML customisation'))
                    ->description(__('Customise your CSS and JS, or add content to your page body'))
                    ->collapsible()
                    ->collapsed(true)
                     ->schema([
                         CodeField::make('custom_css')
                             ->label(__('CSS customisation'))
                             ->setLanguage(CodeField::CSS)
                             ->withLineNumbers()
                             ->minHeight(400),
                         CodeField::make('custom_js')
                             ->label(__('JavaScript customisation'))
                             ->setLanguage(CodeField::JS)
                             ->withLineNumbers()
                             ->minHeight(400),
                         CodeField::make('custom_header')
                             ->label(__('Header customisation'))
                             ->setLanguage(CodeField::HTML)
                             ->withLineNumbers()
                             ->minHeight(400),
                         CodeField::make('custom_body')
                             ->label(__('Body customisation'))
                             ->setLanguage(CodeField::HTML)
                             ->withLineNumbers()
                             ->minHeight(400),
                     ])
                ,
                Section::make(__('Social links'))
                    ->description(__('Set your social media links'))
                    ->collapsible()
                    ->collapsed(true)
                    ->schema([
                        TextInput::make('instagram')
                            ->label('Instagram')
                            ->placeholder(__('Your Instagram URL'))
                            ->url()
                            ->maxLength(191),
                        TextInput::make('facebook')
                            ->label('Facebook')
                            ->placeholder(__('Your Facebook URL'))
                            ->url()
                            ->maxLength(191),
                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->placeholder(__('Your Telegram URL'))
                            ->url()
                            ->maxLength(191),
                        TextInput::make('twitter')
                            ->label('Twitter')
                            ->placeholder(__('Your Twitter URL'))
                            ->url()
                            ->maxLength(191),
                        TextInput::make('whastapp')
                            ->label(__('WhatsApp'))
                            ->placeholder(__('Your WhatsApp URL'))
                            ->url()
                            ->maxLength(191),
                        TextInput::make('youtube')
                            ->label(__('YouTube'))
                            ->placeholder(__('Your YouTube URL'))
                            ->url()
                            ->maxLength(191),
                    ])->columns(2)
                ,
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

            $custom = CustomLayout::current();

            if(!empty($custom)) {
                if($custom->update($this->data)) {

                    Cache::put(\App\Core\Tenancy\Tenancy::key('custom'), $custom);

                    Notification::make()
                        ->title(__('Data updated'))
                        ->body(__('Data updated successfully.'))
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
