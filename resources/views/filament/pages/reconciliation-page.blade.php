<x-filament-panels::page>
    @php $latest = $this->getLatest(); @endphp

    @if (! $latest)
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('No reconciliation has run yet. It runs every hour, or use "Run now".') }}</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="text-xs text-gray-500">{{ __('Result') }}</div>
                <div class="text-2xl font-bold {{ $latest->passed() ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $latest->passed() ? __('Match') : __('Differences found') }}
                </div>
                <div class="text-xs text-gray-500">{{ $latest->finished_at->diffForHumans() }} · {{ __($latest->trigger) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-xs text-gray-500">{{ __('Wallet balances checked') }}</div>
                <div class="text-2xl font-bold tabular-nums">{{ $latest->buckets_checked }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-xs text-gray-500">{{ __('Mismatches') }} / {{ __('Unbalanced journals') }}</div>
                <div class="text-2xl font-bold tabular-nums">{{ $latest->mismatches }} / {{ $latest->unbalanced_journals }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-xs text-gray-500">{{ __('Legacy (observed) journals') }}</div>
                <div class="text-2xl font-bold tabular-nums">{{ $latest->observed_journals }}</div>
            </x-filament::section>
        </div>

        @if ($latest->mismatches > 0)
            <x-filament::section>
                <x-slot name="heading">{{ __('Mismatches') }}</x-slot>
                <x-slot name="description">{{ __('The wallet balance differs from the sum of its ledger entries. Investigate before correcting: the ledger is the source of truth.') }}</x-slot>
                <table class="w-full text-sm tabular-nums">
                    <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Wallet') }}</th><th>{{ __('User') }}</th><th>{{ __('Balance') }}</th><th class="text-right">{{ __('Wallet balance') }}</th><th class="text-right">{{ __('Ledger') }}</th><th class="text-right">{{ __('Difference') }}</th></tr></thead>
                    <tbody>
                    @foreach ($latest->details ?? [] as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="py-1.5">#{{ $row['wallet_id'] }}</td><td>#{{ $row['user_id'] }}</td><td>{{ $row['bucket'] }}</td>
                            <td class="text-right">{{ $row['projection'] }}</td><td class="text-right">{{ $row['ledger'] }}</td><td class="text-right font-semibold">{{ $row['difference'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if ($latest->mismatches > count($latest->details ?? []))
                    <p class="mt-2 text-xs text-gray-500">{{ __('Showing the first :count. Run php artisan wallet:reconcile for the full list.', ['count' => count($latest->details ?? [])]) }}</p>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">{{ __('History') }}</x-slot>
            <table class="w-full text-sm tabular-nums">
                <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Finished') }}</th><th>{{ __('Trigger') }}</th><th class="text-right">{{ __('Wallet balances checked') }}</th><th class="text-right">{{ __('Mismatches') }}</th><th class="text-right">{{ __('Unbalanced journals') }}</th></tr></thead>
                <tbody>
                @foreach ($this->getHistory() as $run)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="py-1.5">{{ \App\Core\Localization\Formatter::dateTime($run->finished_at) }}</td><td>{{ __($run->trigger) }}</td>
                        <td class="text-right">{{ $run->buckets_checked }}</td><td class="text-right">{{ $run->mismatches }}</td><td class="text-right">{{ $run->unbalanced_journals }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
