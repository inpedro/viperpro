<?php

namespace App\Filament\Admin\Pages;

use App\Core\Audit\Audit;
use App\Core\Localization\Locales;
use App\Core\Localization\RegistrationPolicy;
use App\Core\Tenancy\Tenancy;
use App\Models\Setting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/** Per-casino languages, timezone and registration fields (Phase 5). */
class LocalizationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $slug = 'localization';

    protected static string $view = 'filament.pages.localization-settings-page';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Languages & registration');
    }

    public function getTitle(): string
    {
        return __('Languages & registration');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin') && Tenancy::id() !== null;
    }

    public function mount(): void
    {
        $s = Setting::query()->firstOrFail();
        $enabled = json_decode((string) ($s->getAttributes()['enabled_locales'] ?? '[]'), true) ?: [Locales::tenantDefault()];

        $this->form->fill([
            'default_locale'  => Locales::tenantDefault(),
            'enabled_locales' => $enabled,
            'timezone'        => Locales::timezone(),
            'country_code'    => $s->country_code,
            'phone_field'     => $s->phone_field ?? 'required',
            'document_field'  => $s->document_field ?? 'hidden',
            'document_type'   => $s->document_type,
        ]);
    }

    public function form(Form $form): Form
    {
        $locales = array_map(fn ($m) => $m['native'].' ('.$m['name'].')', Locales::installed());
        $modes = array_combine(RegistrationPolicy::MODES, array_map(fn ($m) => __(ucfirst($m)), RegistrationPolicy::MODES));

        return $form->schema([
            Section::make(__('Languages'))->schema([
                CheckboxList::make('enabled_locales')->label(__('Languages offered'))->options($locales)->required()->columns(2),
                Select::make('default_locale')->label(__('Default language'))->options($locales)->required()
                    ->rule(fn ($get) => fn ($attr, $value, $fail) => in_array($value, (array) $get('enabled_locales'), true) ?: $fail(__('The default language must be one of the languages offered.'))),
                Select::make('timezone')->label(__('Display timezone'))->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable()->required(),
                TextInput::make('country_code')->label(__('Main market (ISO country code)'))->maxLength(2)->alpha(),
            ])->columns(2),
            Section::make(__('Registration fields'))->schema([
                Select::make('phone_field')->label(__('Phone number'))->options($modes)->required(),
                Select::make('document_field')->label(__('National ID'))->options($modes)->required(),
                Select::make('document_type')->label(__('ID type'))->options([
                    'cpf' => __('CPF (Brazil, checksum validated)'), 'national_id' => __('National ID'), 'passport' => __('Passport'),
                ])->placeholder(__('Free text')),
            ])->columns(3),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $enabled = array_values(array_filter((array) $data['enabled_locales'], [Locales::class, 'isInstalled']));

        Setting::query()->firstOrFail()->update([
            'enabled_locales' => json_encode($enabled),
            'default_locale'  => in_array($data['default_locale'], $enabled, true) ? $data['default_locale'] : $enabled[0],
            'timezone'        => $data['timezone'],
            'country_code'    => $data['country_code'] ? strtoupper($data['country_code']) : null,
            'phone_field'     => $data['phone_field'],
            'document_field'  => $data['document_field'],
            'document_type'   => $data['document_type'] ?: null,
        ]);

        Cache::forget(Tenancy::key('setting'));
        Audit::record('settings.localization_updated', 'tenant', Tenancy::id(), ['locales' => $enabled]);
        Notification::make()->title(__('Settings saved'))->success()->send();
    }
}
