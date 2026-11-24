<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        @if ($preview = $this->getPreview())
            <x-filament::section>
                <x-slot name="heading">{{ __('Preview') }}</x-slot>
                <div style="background: {{ $preview['colors']['bg'] }}; color: {{ $preview['colors']['text'] }}; border-radius: {{ $preview['radius'] }}px; padding: 20px;">
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                        @foreach (['bg', 'surface', 'surface-2', 'border', 'primary', 'accent', 'success', 'danger'] as $token)
                            <div style="text-align:center; font-size:11px; color: {{ $preview['colors']['muted'] }}">
                                <div style="width:56px; height:40px; background: {{ $preview['colors'][$token] }}; border:1px solid {{ $preview['colors']['border'] }}; border-radius: {{ max(0, (int) round($preview['radius'] / 2)) }}px;"></div>
                                {{ $token }}
                            </div>
                        @endforeach
                        <span style="background: {{ $preview['colors']['primary'] }}; color: {{ $preview['colors']['primary-contrast'] }}; padding: 10px 18px; border-radius: {{ $preview['radius'] }}px; font-weight:700;">{{ __('Play now') }}</span>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::button type="submit">{{ __('Save') }}</x-filament::button>
    </form>
</x-filament-panels::page>
