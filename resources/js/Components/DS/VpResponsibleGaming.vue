<template>
    <div class="flex flex-col md:flex-row md:items-center gap-3 rounded-vp border border-vp-border bg-vp-surface p-4 text-sm text-vp-muted">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-vp-danger font-bold text-vp-text" aria-hidden="true">18+</span>
        <p class="flex-1">
            {{ $t('Gambling can be addictive. Play responsibly and only bet what you can afford to lose.') }}
            {{ $t('Players must be 18 or older (or the legal age in their jurisdiction).') }}
        </p>
        <RouterLink :to="{ name: 'responsibleGaming' }" class="shrink-0 font-semibold text-vp-primary hover:underline">{{ $t('Responsible gaming') }}</RouterLink>
    </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';
</script>
