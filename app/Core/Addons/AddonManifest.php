<?php

namespace App\Core\Addons;

use Illuminate\Support\Facades\Validator;

/**
 * A validated addon.json. Schema (all keys besides the required ones are optional):
 *
 * {
 *   "id": "payment.stripe",                       required, lowercase dotted
 *   "name": "Stripe Payments",                    required
 *   "version": "1.0.0",                           required, semver
 *   "description": "...", "author": "...",
 *   "category": "payments",                       required, see CATEGORIES
 *   "requires": { "platform": "^1.0", "addons": { "core.kyc": "^1.0" } },
 *   "autoload": { "psr-4": { "Addons\\Payments\\Stripe\\": "src/" } },
 *   "providers": ["Addons\\Payments\\Stripe\\StripeServiceProvider"],
 *   "contributes": { "payment_gateways": { "stripe": "Addons\\Payments\\Stripe\\StripeGateway" } },
 *   "permissions": ["payments.stripe.configure"],
 *   "config": [ { "key": "secret_key", "label": "Secret key", "type": "string", "secret": true, "required": true } ],
 *   "migrations": "database/migrations",
 *   "docs": "README.md"
 * }
 */
final class AddonManifest
{
    public const CATEGORIES = [
        'payments', 'game_providers', 'bonuses', 'affiliates', 'crm', 'notifications',
        'analytics', 'kyc', 'responsible_gaming', 'promotions', 'themes', 'localization', 'other',
    ];

    public const CONFIG_TYPES = ['string', 'text', 'url', 'integer', 'boolean', 'select'];

    private function __construct(public readonly array $data, public readonly string $path)
    {
    }

    /**
     * @throws InvalidManifest
     */
    public static function fromDirectory(string $path): self
    {
        $file = rtrim($path, '/').'/addon.json';
        if (! is_file($file)) {
            throw new InvalidManifest("No addon.json in {$path}");
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            throw new InvalidManifest("addon.json in {$path} is not valid JSON");
        }

        return self::fromArray($data, rtrim($path, '/'));
    }

    /**
     * @throws InvalidManifest
     */
    public static function fromArray(array $data, string $path = ''): self
    {
        $classPattern = 'regex:/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

        $validator = Validator::make($data, [
            'id'                       => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*(\.[a-z0-9_-]+)+$/'],
            'name'                     => ['required', 'string', 'max:120'],
            'version'                  => ['required', 'string', fn ($a, $v, $fail) => Semver::isValid((string) $v) ?: $fail('version must be semver (1.2.3)')],
            'description'              => ['nullable', 'string', 'max:1000'],
            'author'                   => ['nullable', 'string', 'max:120'],
            'category'                 => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'requires'                 => ['nullable', 'array'],
            'requires.platform'        => ['nullable', 'string', 'max:60'],
            'requires.addons'          => ['nullable', 'array'],
            'requires.addons.*'        => ['string', 'max:60'],
            'autoload'                 => ['nullable', 'array'],
            'autoload.psr-4'           => ['nullable', 'array'],
            'autoload.psr-4.*'         => ['string', 'regex:/^[A-Za-z0-9_\/-]+\/?$/', 'not_regex:/\.\./'],
            'providers'                => ['nullable', 'array'],
            'providers.*'              => ['string', $classPattern],
            'contributes'              => ['nullable', 'array'],
            'contributes.payment_gateways'   => ['nullable', 'array'],
            'contributes.payment_gateways.*' => ['string', $classPattern],
            'permissions'              => ['nullable', 'array'],
            'permissions.*'            => ['string', 'max:120', 'regex:/^[a-z0-9_.-]+$/'],
            'config'                   => ['nullable', 'array'],
            'config.*.key'             => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'config.*.label'           => ['required', 'string', 'max:120'],
            'config.*.type'            => ['required', 'in:'.implode(',', self::CONFIG_TYPES)],
            'config.*.secret'          => ['nullable', 'boolean'],
            'config.*.required'        => ['nullable', 'boolean'],
            'config.*.options'         => ['nullable', 'array'],
            'migrations'               => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\/-]+$/', 'not_regex:/\.\./'],
            'docs'                     => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\/.-]+$/', 'not_regex:/\.\./'],
        ]);

        if ($validator->fails()) {
            throw new InvalidManifest('Invalid addon manifest'.($data['id'] ?? '' ? " [{$data['id']}]" : '').': '.implode(' ', $validator->errors()->all()));
        }

        $keys = array_column($data['config'] ?? [], 'key');
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidManifest("Duplicate config keys in [{$data['id']}]");
        }

        return new self($data, $path);
    }

    public function id(): string { return $this->data['id']; }

    public function name(): string { return $this->data['name']; }

    public function version(): string { return $this->data['version']; }

    public function category(): string { return $this->data['category']; }

    public function platformConstraint(): string { return $this->data['requires']['platform'] ?? '*'; }

    /** @return array<string, string> addon id => version constraint */
    public function addonDependencies(): array { return $this->data['requires']['addons'] ?? []; }

    /** @return array<string, string> namespace prefix => absolute directory */
    public function psr4(): array
    {
        $map = [];
        foreach ($this->data['autoload']['psr-4'] ?? [] as $prefix => $dir) {
            $map[$prefix] = $this->path.'/'.trim($dir, '/').'/';
        }

        return $map;
    }

    public function providers(): array { return $this->data['providers'] ?? []; }

    /** @return array<string, class-string> */
    public function paymentGateways(): array { return $this->data['contributes']['payment_gateways'] ?? []; }

    public function permissions(): array { return $this->data['permissions'] ?? []; }

    public function configSchema(): array { return $this->data['config'] ?? []; }

    public function migrationsPath(): ?string
    {
        return isset($this->data['migrations']) ? $this->path.'/'.trim($this->data['migrations'], '/') : null;
    }
}
