<template>
    <section class="mb-10" :aria-label="title">
        <VpSectionHeader :title="title" :icon="icon">
            <button type="button" class="hidden md:inline-flex h-8 w-8 items-center justify-center rounded-full bg-vp-surface-2 text-vp-muted hover:text-vp-text" :aria-label="$t('Previous')" @click="scroll(-1)">
                <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i>
            </button>
            <button type="button" class="hidden md:inline-flex h-8 w-8 items-center justify-center rounded-full bg-vp-surface-2 text-vp-muted hover:text-vp-text" :aria-label="$t('Next')" @click="scroll(1)">
                <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
            </button>
            <VpButton v-if="seeAll" variant="ghost" size="sm" @click="$emit('see-all')">{{ $t('See all') }}</VpButton>
        </VpSectionHeader>

        <div ref="track" class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-2 scrollbar-none" style="scrollbar-width: none">
            <div v-for="(game, i) in (loading ? placeholders : games)" :key="loading ? 'ph' + i : game.id"
                 class="snap-start shrink-0 w-[42%] sm:w-[28%] md:w-[20%] lg:w-[15%] xl:w-[12.5%]">
                <VpGameCard :game="loading ? null : game" :loading="loading" />
            </div>
        </div>
        <p v-if="!loading && games.length === 0" class="text-sm text-vp-muted">{{ $t('No data to show') }}</p>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import VpSectionHeader from './VpSectionHeader.vue';
import VpGameCard from './VpGameCard.vue';
import VpButton from './VpButton.vue';

defineProps({
    title: String, icon: String,
    games: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    seeAll: { type: Boolean, default: true },
});
defineEmits(['see-all']);

const placeholders = Array.from({ length: 8 });
const track = ref(null);
const scroll = (dir) => track.value?.scrollBy({ left: dir * track.value.clientWidth * 0.8, behavior: 'smooth' });
</script>
