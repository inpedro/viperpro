<?php

namespace App\Filament\Admin\Pages;

use App\Core\Audit\Audit;
use App\Core\Tenancy\Tenancy;
use App\Core\Theming\Theme;
use App\Models\CustomLayout;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/** Casino theme: preset + safe brand overrides (Phase 6). */
class ThemePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $slug = 'theme';

    protected static string $view = 'filament.pages.theme-page';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Theme');
    }

    public function getTitle(): string
    {
        return __('Theme');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin') && Tenancy::id() !== null;
    }

    public function mount(): void
    {
        $layout = CustomLayout::query()->firstOrFail();
        $raw = $layout->getAttributes()['theme_overrides'] ?? null;
        $overrides = Theme::sanitizeOverrides(is_string($raw) ? (json_decode($raw, true) ?: []) : []);

        $this->form->fill([
            'theme'   => $layout->theme ?? 'legacy',
            'primary' => $overrides['primary'] ?? null,
            'accent'  => $overrides['accent'] ?? null,
            'radius'  => $overrides['radius'] ?? null,
            'font'    => $overrides['font'] ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        $options = ['legacy' => __('Current custom colours (legacy)')];
        foreach (Theme::presets() as $key => $p) {
            $options[$key] = $p['label'].' · '.__($p['description']);
        }
        $fonts = array_combine(array_keys((array) config('themes.fonts')), array_map(fn ($k) => ucwords(str_replace('-', ' ', $k)), array_keys((array) config('themes.fonts'))));

        return $form->schema([
            Section::make(__('Theme'))->schema([
                Radio::make('theme')->label(__('Theme'))->options($options)->required(),
            ]),
            Section::make(__('Brand overrides'))->description(__('Optional. Leave empty to use the theme defaults.'))->schema([
                ColorPicker::make('primary')->label(__('Primary colour'))->regex('/^#[0-9A-Fa-f]{6}$/'),
                ColorPicker::make('accent')->label(__('Accent colour'))->regex('/^#[0-9A-Fa-f]{6}$/'),
                TextInput::make('radius')->label(__('Corner radius (px)'))->numeric()->minValue(Theme::RADIUS_MIN)->maxValue(Theme::RADIUS_MAX),
                Select::make('font')->label(__('Font'))->options($fonts)->placeholder(__('Theme default')),
            ])->columns(4),
        ])->statePath('data');
    }

    /** Swatches for the preview (computed server-side from validated values). */
    public function getPreview(): ?array
    {
        $key = $this->data['theme'] ?? null;
        if (! Theme::exists($key)) {
            return null;
        }

        return Theme::tokens($key, array_filter([
            'primary' => $this->data['primary'] ?? null,
            'accent'  => $this->data['accent'] ?? null,
            'radius'  => $this->data['radius'] ?? null,
            'font'    => $this->data['font'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $theme = Theme::exists($data['theme'] ?? null) ? $data['theme'] : null;
        $overrides = Theme::sanitizeOverrides(array_filter($data, fn ($v) => $v !== null && $v !== ''));

        CustomLayout::query()->firstOrFail()->update([
            'theme'           => $theme,
            'theme_overrides' => json_encode($overrides),
        ]);

        Cache::forget(Tenancy::key('custom'));
        Audit::record('settings.theme_updated', 'tenant', Tenancy::id(), ['theme' => $theme ?? 'legacy', 'overrides' => array_keys($overrides)]);
        Notification::make()->title(__('Theme saved'))->success()->send();
    }
}
