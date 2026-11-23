<?php

namespace App\Filament\Admin\Pages;

use App\Core\Addons\AddonException;
use App\Core\Addons\AddonManager;
use App\Core\Payments\PaymentGateways;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Addon manager: view, install, enable/disable, update, configure and health-check addons.
 * Code never arrives through this page: only addons already present in the configured addon
 * paths (deployed via the release pipeline) and passing integrity/signature checks can be installed.
 */
class AddonManagerPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Addons';

    protected static ?string $title = 'Addons';

    protected static ?string $slug = 'addons';

    protected static string $view = 'filament.pages.addon-manager-page';

    public ?string $configuring = null;

    public ?array $configData = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    /** @return array<int, array<string, mixed>> */
    public function getAddons(): array
    {
        $manager = app(AddonManager::class);
        $rows = [];

        foreach ($manager->discover(true) as $id => $manifest) {
            $installed = $manager->installedOrNull($id);
            $health = null;

            foreach (array_keys($manifest->paymentGateways()) as $gatewayId) {
                if (PaymentGateways::has($gatewayId)) {
                    $status = PaymentGateways::get($gatewayId)->getHealthStatus();
                    $health = $status->status.($status->messages ? ': '.implode(' ', $status->messages) : '');
                }
            }

            $rows[] = [
                'id'          => $id,
                'name'        => $manifest->name(),
                'version'     => $manifest->version(),
                'installed'   => $installed?->version,
                'category'    => $manifest->category(),
                'trust'       => $manager->trustLevel($id),
                'description' => $manifest->data['description'] ?? '',
                'state'       => $installed ? ($installed->enabled ? 'enabled' : 'installed') : 'available',
                'problems'    => $manager->check($id),
                'health'      => $health,
                'configurable'=> $installed !== null && $manifest->configSchema() !== [],
            ];
        }

        return $rows;
    }

    public function getInvalidManifests(): array
    {
        return app(AddonManager::class)->invalidManifests();
    }

    public function runAction(string $action, string $id): void
    {
        $manager = app(AddonManager::class);

        try {
            match ($action) {
                'install' => $manager->install($id),
                'enable'  => $manager->enable($id),
                'disable' => $manager->disable($id),
                'update'  => $manager->update($id),
                default   => throw new AddonException('Unknown action'),
            };
            Notification::make()->title(ucfirst($action).": {$id}")->success()->send();
        } catch (AddonException $e) {
            Notification::make()->title('Addon action failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function openConfig(string $id): void
    {
        $this->configuring = $id;
        $masked = app(AddonManager::class)->maskedConfig($id);

        // Secrets are never sent back to the browser; an empty field keeps the stored value.
        $schema = app(AddonManager::class)->manifest($id)->configSchema();
        foreach ($schema as $field) {
            if ($field['secret'] ?? false) {
                $masked[$field['key']] = null;
            }
        }

        $this->form->fill($masked);
    }

    public function closeConfig(): void
    {
        $this->configuring = null;
        $this->configData = [];
    }

    public function saveConfig(): void
    {
        if ($this->configuring === null) {
            return;
        }

        $errors = app(AddonManager::class)->configure($this->configuring, $this->form->getState());

        if ($errors === []) {
            Notification::make()->title('Configuration saved')->success()->send();
            $this->closeConfig();
        } else {
            Notification::make()->title('Configuration not saved')->body(implode(' ', $errors))->danger()->send();
        }
    }

    public function form(Form $form): Form
    {
        $fields = [];

        if ($this->configuring !== null) {
            foreach (app(AddonManager::class)->manifest($this->configuring)->configSchema() as $field) {
                $key = $field['key'];
                $component = match ($field['type']) {
                    'boolean' => Toggle::make($key),
                    'select'  => Select::make($key)->options(array_combine($field['options'] ?? [], $field['options'] ?? [])),
                    'text'    => Textarea::make($key),
                    'integer' => TextInput::make($key)->numeric(),
                    'url'     => TextInput::make($key)->url()->helperText('Must start with https://'),
                    default   => TextInput::make($key),
                };

                $component = $component->label($field['label']);

                if ($field['secret'] ?? false) {
                    $component = $component->password()->revealable(false)->helperText('Stored encrypted. Leave blank to keep the current value.');
                } elseif ($field['required'] ?? false) {
                    $component = $component->required();
                }

                $fields[] = $component;
            }
        }

        return $form->schema([Section::make($this->configuring ?? '')->schema($fields)])->statePath('configData');
    }
}
