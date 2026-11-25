<x-filament-panels::page>
    @php $stats = $this->getStats(); @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach (['accepted' => 'success', 'ignored' => 'gray', 'rejected' => 'danger', 'error' => 'warning'] as $outcome => $color)
            <x-filament::section>
                <div class="text-sm text-gray-500">{{ __('Webhooks') }} · {{ $outcome }} · 24h</div>
                <div class="text-2xl font-bold">{{ $stats[$outcome] ?? 0 }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('Health') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500">
                    <th class="py-2 pe-4">{{ __('Type') }}</th><th class="pe-4">{{ __('Name') }}</th><th class="pe-4">{{ __('Status') }}</th>
                    <th class="pe-4">{{ __('Capabilities') }}</th><th class="pe-4">{{ __('Payouts') }}</th><th>{{ __('Notes') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($this->getHealth() as $row)
                    <tr class="border-t border-gray-200 dark:border-gray-700 align-top">
                        <td class="py-2 pe-4">{{ $row['type'] }}</td>
                        <td class="pe-4 font-medium">{{ $row['name'] }}</td>
                        <td class="pe-4"><x-filament::badge :color="match($row['status']) { 'ok' => 'success', 'unverified' => 'warning', 'degraded' => 'warning', default => 'gray' }">{{ $row['status'] }}</x-filament::badge></td>
                        <td class="pe-4 text-gray-500">{{ $row['capabilities'] }}</td>
                        <td class="pe-4 text-gray-500">{{ $row['payouts'] }}</td>
                        <td class="text-gray-500">{{ $row['notes'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Webhook monitor') }}</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
