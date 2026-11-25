<x-filament-panels::page>
    <x-filament::section>
        <table class="w-full text-sm tabular-nums">
            <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Casino') }}</th><th>{{ __('Result') }}</th><th>{{ __('Finished') }}</th><th class="text-right">{{ __('Wallet balances checked') }}</th><th class="text-right">{{ __('Mismatches') }}</th><th class="text-right">{{ __('Unbalanced journals') }}</th><th class="text-right">{{ __('Legacy (observed) journals') }}</th></tr></thead>
            <tbody>
            @foreach ($this->getRows() as $row)
                @php $run = $row['run']; @endphp
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td class="py-1.5">{{ $row['tenant']->name }}</td>
                    @if ($run)
                        <td class="{{ $run->passed() ? 'text-success-600' : 'text-danger-600 font-semibold' }}">{{ $run->passed() ? __('Match') : __('Differences found') }}</td>
                        <td>{{ $run->finished_at->diffForHumans() }}</td>
                        <td class="text-right">{{ $run->buckets_checked }}</td><td class="text-right">{{ $run->mismatches }}</td>
                        <td class="text-right">{{ $run->unbalanced_journals }}</td><td class="text-right">{{ $run->observed_journals }}</td>
                    @else
                        <td colspan="6" class="text-gray-500">{{ __('No reconciliation has run yet.') }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
