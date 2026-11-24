<x-filament-panels::page>
    @php $state = $this->getState(); @endphp

    @if ($state === 'enroll')
        <x-filament::section>
            <x-slot name="heading">{{ __('Set up two-factor authentication') }}</x-slot>
            <ol class="list-decimal ps-5 space-y-2 text-sm">
                <li>{{ __('Open your authenticator app (Google Authenticator, 1Password, Authy, ...).') }}</li>
                <li>{{ __('Add an account using this key, or open the link on your phone:') }}
                    <div class="mt-2 rounded bg-gray-100 dark:bg-gray-800 p-3 font-mono text-base tracking-widest select-all">{{ trim(chunk_split($this->getPendingSecret() ?? '', 4, ' ')) }}</div>
                    <a href="{{ $this->getProvisioningUri() }}" class="text-primary-600 underline text-xs break-all">{{ __('Open in authenticator app') }}</a>
                </li>
                <li>{{ __('Enter the 6-digit code it shows:') }}</li>
            </ol>
            <form wire:submit="enroll" class="mt-4 flex gap-3 max-w-sm">
                <x-filament::input.wrapper class="flex-1"><x-filament::input type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" /></x-filament::input.wrapper>
                <x-filament::button type="submit">{{ __('Verify') }}</x-filament::button>
            </form>
        </x-filament::section>
    @elseif ($state === 'codes')
        <x-filament::section>
            <x-slot name="heading">{{ __('Recovery codes') }}</x-slot>
            <p class="text-sm">{{ __('Store these codes somewhere safe. Each can be used once if you lose your device. They will not be shown again.') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm max-w-md">
                @foreach ($recoveryCodes as $c)<div class="rounded bg-gray-100 dark:bg-gray-800 px-3 py-2 select-all">{{ $c }}</div>@endforeach
            </div>
            <x-filament::button class="mt-4" wire:click="continueToPanel">{{ __('I have saved them') }}</x-filament::button>
        </x-filament::section>
    @elseif ($state === 'challenge')
        <x-filament::section>
            <x-slot name="heading">{{ __('Verification required') }}</x-slot>
            <p class="text-sm">{{ __('Enter the code from your authenticator app, or a recovery code.') }}</p>
            <form wire:submit="challenge" class="mt-4 flex gap-3 max-w-sm">
                <x-filament::input.wrapper class="flex-1"><x-filament::input type="text" wire:model="code" autocomplete="one-time-code" maxlength="11" /></x-filament::input.wrapper>
                <x-filament::button type="submit">{{ __('Verify') }}</x-filament::button>
            </form>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm">{{ __('Two-factor authentication is active for this session.') }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
