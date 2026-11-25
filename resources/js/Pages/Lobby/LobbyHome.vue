<template>
    <BaseLayout>
        <div class="bg-vp-bg text-vp-text min-h-screen">
            <div class="mx-auto w-full max-w-[1400px] px-4 md:px-6 py-6">

                <!-- Hero: tenant banners, or a neutral welcome when none are configured -->
                <section class="mb-8" :aria-label="$t('Promotions')">
                    <div v-if="banners.length" class="relative overflow-hidden rounded-vp">
                        <div ref="heroTrack" class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth" style="scrollbar-width: none">
                            <a v-for="(banner, i) in banners" :key="banner.id ?? i" :href="banner.link || '#'"
                               class="relative w-full shrink-0 snap-start aspect-[16/6] md:aspect-[16/5] bg-vp-surface-2">
                                <img :src="banner.image_url" :alt="banner.description || $t('Promotion')" class="h-full w-full object-cover" :loading="i === 0 ? 'eager' : 'lazy'" />
                            </a>
                        </div>
                        <div v-if="banners.length > 1" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                            <button v-for="(b, i) in banners" :key="'dot' + i" type="button" @click="goTo(i)"
                                    :aria-label="`${$t('Promotion')} ${i + 1}`"
                                    :class="['h-1.5 rounded-full transition-all', i === heroIndex ? 'w-6 bg-vp-primary' : 'w-1.5 bg-vp-text/40']"></button>
                        </div>
                    </div>
                    <div v-else class="relative overflow-hidden rounded-vp border border-vp-border bg-vp-surface p-8 md:p-12">
                        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-vp-primary/20 blur-3xl" aria-hidden="true"></div>
                        <div class="absolute -bottom-24 right-40 h-60 w-60 rounded-full bg-vp-accent/10 blur-3xl" aria-hidden="true"></div>
                        <p class="text-sm font-semibold uppercase tracking-widest text-vp-primary">{{ $t('Welcome to') }} {{ siteName }}</p>
                        <h1 class="vp-heading mt-2 max-w-2xl text-3xl md:text-5xl font-extrabold leading-tight">{{ $t('Enjoy our exclusive selection of slots, live dealers and original games') }}</h1>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <VpButton size="lg" @click="scrollToGames">{{ $t('Play now') }}</VpButton>
                            <VpButton size="lg" variant="outline" :to="{ name: 'promotion' }">{{ $t('Promotions') }}</VpButton>
                        </div>
                    </div>
                </section>

                <!-- Filters -->
                <section ref="gamesAnchor" class="sticky top-[65px] z-20 -mx-4 md:-mx-6 mb-6 bg-vp-bg/95 px-4 md:px-6 py-3 backdrop-blur" :aria-label="$t('Search')">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center">
                        <label class="relative flex-1 lg:max-w-sm">
                            <span class="sr-only">{{ $t('search the game') }}</span>
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-vp-muted text-sm" aria-hidden="true"></i>
                            <input v-model="filters.q" type="search" :placeholder="$t('search the game')"
                                   class="w-full rounded-vp border border-vp-border bg-vp-surface py-2.5 pl-9 pr-3 text-sm text-vp-text placeholder:text-vp-muted focus:border-vp-primary focus:ring-0" />
                        </label>
                        <div class="flex gap-2 overflow-x-auto" style="scrollbar-width: none" role="group" :aria-label="$t('Categories')">
                            <VpChip :active="!filters.category" @click="filters.category = ''">{{ $t('All Games') }}</VpChip>
                            <VpChip v-for="c in categories" :key="c.slug" :active="filters.category === c.slug" @click="filters.category = c.slug">{{ c.name }}</VpChip>
                        </div>
                        <label class="lg:ml-auto">
                            <span class="sr-only">{{ $t('Provider') }}</span>
                            <select v-model="filters.provider" class="w-full lg:w-56 rounded-vp border border-vp-border bg-vp-surface py-2.5 text-sm text-vp-text focus:border-vp-primary focus:ring-0">
                                <option value="">{{ $t('Provider') }}: {{ $t('All') }}</option>
                                <option v-for="p in providers" :key="p.id" :value="String(p.id)">{{ p.name }} ({{ p.games }})</option>
                            </select>
                        </label>
                    </div>
                </section>

                <!-- Default view: curated rails -->
                <template v-if="!isFiltering">
                    <VpGameRail :title="$t('Featured Games')" icon="fa-solid fa-star" :games="rails.featured" :loading="railsLoading" @see-all="openSection('featured')" />
                    <VpGameRail :title="$t('Popular Games')" icon="fa-solid fa-fire" :games="rails.popular" :loading="railsLoading" @see-all="openSection('popular')" />
                    <VpGameRail :title="$t('New releases')" icon="fa-solid fa-bolt" :games="rails.new" :loading="railsLoading" @see-all="openSection('new')" />

                    <section v-if="promoTiles.length" class="mb-10 grid grid-cols-1 md:grid-cols-3 gap-3" :aria-label="$t('Promotions')">
                        <a v-for="(tile, i) in promoTiles" :key="tile.id ?? i" :href="tile.link || '#'" class="block overflow-hidden rounded-vp ring-1 ring-vp-border/60">
                            <img :src="tile.image_url" :alt="tile.description || $t('Promotion')" class="w-full aspect-[16/7] object-cover transition-transform duration-300 hover:scale-105" loading="lazy" />
                        </a>
                    </section>

                    <section v-if="providers.length" class="mb-10" :aria-label="$t('Providers')">
                        <VpSectionHeader :title="$t('Providers')" icon="fa-solid fa-layer-group" />
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            <button v-for="p in providers" :key="p.id" type="button" @click="filters.provider = String(p.id)"
                                    class="rounded-vp border border-vp-border bg-vp-surface px-4 py-5 text-left transition-colors hover:border-vp-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-vp-primary">
                                <span class="block truncate font-semibold text-vp-text">{{ p.name }}</span>
                                <span class="block text-xs text-vp-muted">{{ p.games }} {{ $t('Games') }}</span>
                            </button>
                        </div>
                    </section>
                </template>

                <!-- Filtered / "see all" view: grid with load more -->
                <section v-else class="mb-10" :aria-label="$t('All Games')">
                    <VpSectionHeader :title="gridTitle" :subtitle="grid.total !== null ? `${grid.total} ${$t('Games')}` : ''">
                        <VpButton variant="ghost" size="sm" @click="resetFilters">{{ $t('Clear filters') }}</VpButton>
                    </VpSectionHeader>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3">
                        <VpGameCard v-for="g in grid.items" :key="g.id" :game="g" />
                        <VpGameCard v-for="i in (grid.loading ? 8 : 0)" :key="'sk' + i" loading />
                    </div>
                    <div v-if="!grid.loading && grid.items.length === 0" class="py-16 text-center text-vp-muted">
                        <i class="fa-regular fa-face-meh text-3xl mb-3 block" aria-hidden="true"></i>
                        {{ $t('No matches were found.') }}
                    </div>
                    <div v-if="grid.page < grid.lastPage" class="mt-6 flex justify-center">
                        <VpButton variant="subtle" :disabled="grid.loading" @click="loadGrid(grid.page + 1)">{{ $t('Load more') }}</VpButton>
                    </div>
                </section>

                <VpResponsibleGaming class="mb-6" />
            </div>
        </div>
    </BaseLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { trans } from 'laravel-vue-i18n';
import BaseLayout from '@/Layouts/BaseLayout.vue';
import HttpApi from '@/Services/HttpApi.js';
import { useSettingStore } from '@/Stores/SettingStore.js';
import VpButton from '@/Components/DS/VpButton.vue';
import VpChip from '@/Components/DS/VpChip.vue';
import VpSectionHeader from '@/Components/DS/VpSectionHeader.vue';
import VpGameCard from '@/Components/DS/VpGameCard.vue';
import VpGameRail from '@/Components/DS/VpGameRail.vue';
import VpResponsibleGaming from '@/Components/DS/VpResponsibleGaming.vue';

const route = useRoute();
const router = useRouter();
const settings = useSettingStore();
const siteName = computed(() => settings.setting?.software_name || '');

const banners = ref([]);
const promoTiles = ref([]);
const categories = ref([]);
const providers = ref([]);
const rails = reactive({ featured: [], popular: [], new: [] });
const railsLoading = ref(true);

// Filters live in the URL so a filtered lobby can be shared and survives reloads.
const filters = reactive({
    q: String(route.query.q || ''),
    category: String(route.query.category || ''),
    provider: String(route.query.provider || ''),
    section: String(route.query.section || ''),
});
const isFiltering = computed(() => Boolean(filters.q.trim().length >= 2 || filters.category || filters.provider || filters.section));
const grid = reactive({ items: [], page: 1, lastPage: 1, total: null, loading: false });

const gridTitle = computed(() => {
    if (filters.q.trim()) return `${trans('Search')}: “${filters.q.trim()}”`;
    if (filters.section === 'featured') return trans('Featured Games');
    if (filters.section === 'popular') return trans('Popular Games');
    if (filters.section === 'new') return trans('New releases');
    const c = categories.value.find(x => x.slug === filters.category);
    const p = providers.value.find(x => String(x.id) === filters.provider);
    return [c?.name, p?.name].filter(Boolean).join(' · ') || trans('All Games');
});

const fetchGames = (params) => HttpApi.get('lobby/games', { params }).then(r => r.data);

async function loadRails() {
    railsLoading.value = true;
    try {
        const [f, p, n] = await Promise.all(['featured', 'popular', 'new'].map(section => fetchGames({ section, per_page: 16 })));
        rails.featured = f.data; rails.popular = p.data; rails.new = n.data;
    } catch (e) { /* rails stay empty */ }
    railsLoading.value = false;
}

let gridRequest = 0;
async function loadGrid(page = 1) {
    const ticket = ++gridRequest;
    grid.loading = true;
    if (page === 1) grid.items = [];
    try {
        const res = await fetchGames({
            section: filters.section || 'all',
            q: filters.q.trim().length >= 2 ? filters.q.trim() : undefined,
            category: filters.category || undefined,
            provider: filters.provider || undefined,
            per_page: 24,
            page,
        });
        if (ticket !== gridRequest) return; // a newer filter change won
        grid.items = page === 1 ? res.data : grid.items.concat(res.data);
        grid.page = res.meta.current_page; grid.lastPage = res.meta.last_page; grid.total = res.meta.total;
    } catch (e) { /* keep what we have */ }
    if (ticket === gridRequest) grid.loading = false;
}

let debounce = null;
watch(filters, () => {
    router.replace({ query: Object.fromEntries(Object.entries(filters).filter(([, v]) => v)) });
    clearTimeout(debounce);
    if (isFiltering.value) debounce = setTimeout(() => loadGrid(1), 250);
});

function openSection(section) { filters.section = section; scrollToGames(); }
function resetFilters() { Object.assign(filters, { q: '', category: '', provider: '', section: '' }); }

const gamesAnchor = ref(null);
function scrollToGames() { gamesAnchor.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

// Hero carousel
const heroTrack = ref(null);
const heroIndex = ref(0);
let heroTimer = null;
function goTo(i) {
    heroIndex.value = i;
    heroTrack.value?.scrollTo({ left: i * heroTrack.value.clientWidth, behavior: 'smooth' });
}
const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

onMounted(async () => {
    loadRails();
    if (isFiltering.value) loadGrid(1);

    const [b, c, p] = await Promise.allSettled([
        HttpApi.get('settings/banners'), HttpApi.get('lobby/categories'), HttpApi.get('lobby/providers'),
    ]);
    if (b.status === 'fulfilled') {
        const all = (b.value.data.banners || []).filter(x => x.image)
            .map(x => ({ ...x, image_url: /^https?:\/\//.test(x.image) ? x.image : `/storage/${x.image}` }));
        banners.value = all.filter(x => !x.type || x.type === 'carousel');
        promoTiles.value = all.filter(x => x.type === 'home').slice(0, 3);
    }
    if (c.status === 'fulfilled') categories.value = c.value.data.data;
    if (p.status === 'fulfilled') providers.value = p.value.data.data;

    if (banners.value.length > 1 && !reduceMotion) {
        heroTimer = setInterval(() => goTo((heroIndex.value + 1) % banners.value.length), 6000);
    }
});
onBeforeUnmount(() => { clearInterval(heroTimer); clearTimeout(debounce); });
</script>
