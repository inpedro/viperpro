<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Last 30 days') }}</x-slot>
        <table class="w-full text-sm tabular-nums">
            <thead><tr class="text-left text-gray-500"><th class="py-2">Casino</th><th>{{ __('Status') }}</th><th>{{ __('Currency') }}</th><th class="text-right">{{ __('Deposits') }}</th><th class="text-right">{{ __('Withdrawals') }}</th><th class="text-right">GGR</th><th class="text-right">{{ __('Active players') }}</th><th class="text-right">{{ __('New registrations') }}</th></tr></thead>
            <tbody>
            @foreach ($this->getRows() as $row)
                @forelse ($row['summary']['currencies'] as $currency => $m)
                    <tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-1.5 font-medium">{{ $row['tenant']->name }}</td><td>{{ $row['tenant']->status }}</td><td>{{ $currency }}</td><td class="text-right">{{ $m['deposits'] }}</td><td class="text-right">{{ $m['withdrawals'] }}</td><td class="text-right">{{ $m['ggr'] }}</td><td class="text-right">{{ $m['active_players'] }}</td><td class="text-right">{{ $row['summary']['registrations'] }}</td></tr>
                @empty
                    <tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-1.5 font-medium">{{ $row['tenant']->name }}</td><td>{{ $row['tenant']->status }}</td><td colspan="5" class="text-gray-500">—</td><td class="text-right">{{ $row['summary']['registrations'] }}</td></tr>
                @endforelse
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
