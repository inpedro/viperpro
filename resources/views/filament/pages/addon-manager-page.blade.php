<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Addons are deployed with your release (git/composer) and verified before they can be installed or enabled.
        Uploading code through the admin panel is not supported.
    </p>

    @foreach ($this->getInvalidManifests() as $dir => $error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-300">
            <strong>Invalid addon</strong> {{ $dir }}: {{ $error }}
        </div>
    @endforeach

    <div class="grid gap-4">
        @foreach ($this->getAddons() as $addon)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $addon['name'] }}
                    <span class="text-xs font-normal text-gray-500">{{ $addon['id'] }} · v{{ $addon['version'] }} · {{ $addon['category'] }} · {{ $addon['trust'] }}</span>
                </x-slot>
                <x-slot name="headerEnd">
                    <x-filament::badge :color="match($addon['state']) { 'enabled' => 'success', 'installed' => 'warning', default => 'gray' }">
                        {{ $addon['state'] }}
                    </x-filament::badge>
                </x-slot>

                <p class="text-sm">{{ $addon['description'] }}</p>

                @if ($addon['health'])
                    <p class="mt-2 text-sm"><strong>Health:</strong> {{ $addon['health'] }}</p>
                @endif

                @if ($addon['problems'])
                    <ul class="mt-2 list-disc ps-5 text-sm text-danger-600">
                        @foreach ($addon['problems'] as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($addon['state'] === 'available')
                        <x-filament::button size="sm" wire:click="runAction('install', '{{ $addon['id'] }}')" wire:confirm="Install {{ $addon['id'] }}? Its migrations will run.">Install</x-filament::button>
                    @endif
                    @if ($addon['state'] === 'installed')
                        <x-filament::button size="sm" color="success" wire:click="runAction('enable', '{{ $addon['id'] }}')" wire:confirm="Enable {{ $addon['id'] }}?">Enable</x-filament::button>
                    @endif
                    @if ($addon['state'] === 'enabled')
                        <x-filament::button size="sm" color="danger" wire:click="runAction('disable', '{{ $addon['id'] }}')" wire:confirm="Disable {{ $addon['id'] }}?">Disable</x-filament::button>
                    @endif
                    @if ($addon['installed'] && $addon['installed'] !== $addon['version'])
                        <x-filament::button size="sm" color="warning" wire:click="runAction('update', '{{ $addon['id'] }}')" wire:confirm="Update {{ $addon['id'] }} from {{ $addon['installed'] }} to {{ $addon['version'] }}?">Update</x-filament::button>
                    @endif
                    @if ($addon['configurable'])
                        <x-filament::button size="sm" color="gray" wire:click="openConfig('{{ $addon['id'] }}')">Configure</x-filament::button>
                    @endif
                </div>

                @if ($configuring === $addon['id'])
                    <form wire:submit="saveConfig" class="mt-4 space-y-4">
                        {{ $this->form }}
                        <div class="flex gap-2">
                            <x-filament::button type="submit">Save</x-filament::button>
                            <x-filament::button color="gray" wire:click="closeConfig" type="button">Cancel</x-filament::button>
                        </div>
                    </form>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
