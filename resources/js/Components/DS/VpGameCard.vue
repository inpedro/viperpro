<template>
    <div v-if="loading" class="aspect-[3/4] w-full rounded-vp bg-vp-surface-2 animate-pulse" aria-hidden="true"></div>

    <RouterLink v-else :to="{ name: 'casinoPlayPage', params: { id: game.id, slug: game.code } }"
                class="group relative block aspect-[3/4] w-full overflow-hidden rounded-vp bg-vp-surface-2 ring-1 ring-vp-border/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-vp-primary"
                :aria-label="`${game.name}${game.provider ? ' · ' + game.provider.name : ''}`">
        <img v-if="game.thumbnail && !broken" :src="game.thumbnail" :alt="game.name" loading="lazy" decoding="async"
             @error="broken = true"
             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />
        <div v-else class="flex h-full w-full items-center justify-center p-3 text-center">
            <span class="vp-heading text-sm font-bold text-vp-muted">{{ game.name }}</span>
        </div>

        <div class="absolute left-2 top-2 flex gap-1">
            <span v-if="game.is_new" class="rounded-full bg-vp-accent px-2 py-0.5 text-[10px] font-bold uppercase text-vp-bg">{{ $t('New') }}</span>
            <span v-if="game.has_demo" class="rounded-full bg-vp-surface/80 px-2 py-0.5 text-[10px] font-semibold text-vp-text backdrop-blur">{{ $t('demo') }}</span>
        </div>

        <div class="absolute inset-x-0 bottom-0 flex flex-col justify-end bg-gradient-to-t from-vp-bg via-vp-bg/80 to-transparent p-3 pt-10
                    translate-y-1 opacity-0 transition-all duration-200 group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:opacity-100">
            <span class="truncate text-sm font-semibold text-vp-text">{{ game.name }}</span>
            <span v-if="game.provider" class="truncate text-xs text-vp-muted">{{ game.provider.name }}</span>
            <span class="mt-2 inline-flex w-max items-center gap-1 rounded-vp-sm bg-vp-primary px-3 py-1 text-xs font-bold text-vp-primary-contrast">
                <i class="fa-solid fa-play text-[10px]" aria-hidden="true"></i> {{ $t('Play now') }}
            </span>
        </div>
    </RouterLink>
</template>

<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';

defineProps({ game: { type: Object, default: null }, loading: { type: Boolean, default: false } });
const broken = ref(false);
</script>
