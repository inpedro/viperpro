<x-filament-panels::page>
    @php $summary = $this->getSummary(); @endphp
    <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">{{ __('Period') }}</span>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="days">
                <option value="1">24h</option><option value="7">7 {{ __('days') }}</option><option value="30">30 {{ __('days') }}</option><option value="90">90 {{ __('days') }}</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <span class="text-sm text-gray-500">{{ __('New registrations') }}: <strong>{{ $summary['registrations'] }}</strong></span>
    </div>

    @forelse ($summary['currencies'] as $currency => $m)
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach (['deposits' => __('Deposits'), 'withdrawals' => __('Withdrawals'), 'ggr' => 'GGR', 'bonus_cost' => __('Bonus cost'), 'active_players' => __('Active players')] as $key => $label)
                <x-filament::section>
                    <div class="text-xs text-gray-500">{{ $label }} · {{ $currency }}</div>
                    <div class="text-2xl font-bold tabular-nums">{{ $key === 'active_players' ? $m[$key] : \App\Core\Localization\Formatter::money(\App\Core\Money\Money::of($m[$key], $currency)) }}</div>
                </x-filament::section>
            @endforeach
        </div>
    @empty
        <x-filament::section><p class="text-sm text-gray-500">{{ __('No financial activity in this period.') }}</p></x-filament::section>
    @endforelse

    <x-filament::section>
        <x-slot name="heading">{{ __('Last 14 days') }}</x-slot>
        <table class="w-full text-sm tabular-nums">
            <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Date') }}</th><th>{{ __('Currency') }}</th><th class="text-right">{{ __('Deposits') }}</th><th class="text-right">GGR</th></tr></thead>
            <tbody>
            @foreach ($this->getDaily() as $day => $currencies)
                @foreach ($currencies as $currency => $m)
                    <tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-1.5">{{ $day }}</td><td>{{ $currency }}</td><td class="text-right">{{ $m['deposits'] ?? '0.00' }}</td><td class="text-right">{{ $m['ggr'] ?? '0.00' }}</td></tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">{{ __('Figures come from the ledger. GGR = bets minus wins of the game account.') }}</p>
    </x-filament::section>
</x-filament-panels::page>
