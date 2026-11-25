<template>
    <LegalPage slug="responsible-gaming">
        <section class="mt-10 space-y-6" :aria-label="$t('Your limits and tools')">
            <h2 class="vp-heading text-2xl font-bold">{{ $t('Your limits and tools') }}</h2>

            <div v-if="!isAuth" class="rounded-vp border border-vp-border bg-vp-surface p-6 text-vp-muted">{{ $t('Log in to set deposit limits, take a break or self-exclude.') }}</div>

            <template v-else-if="state">
                <div v-if="state.excluded" class="rounded-vp border border-vp-danger bg-vp-danger/10 p-4 text-vp-text">
                    <template v-if="state.excluded_until === 'permanent'">{{ $t('Your account is permanently self-excluded.') }}</template>
                    <template v-else>{{ $t('Your account is self-excluded until') }} <strong>{{ formatDate(state.excluded_until) }}</strong>.</template>
                    {{ $t('You can still withdraw your balance.') }}
                </div>

                <!-- Deposit limits -->
                <form class="rounded-vp border border-vp-border bg-vp-surface p-6 space-y-4" @submit.prevent="saveLimits">
                    <h3 class="text-lg font-semibold">{{ $t('Deposit limits') }}</h3>
                    <p class="text-sm text-vp-muted">{{ $t('Lower limits apply immediately. Raising or removing a limit takes effect after 24 hours.') }}</p>
                    <div class="grid md:grid-cols-3 gap-4">
                        <label v-for="period in ['daily', 'weekly', 'monthly']" :key="period" class="block">
                            <span class="text-sm font-medium">{{ $t(period) }}</span>
                            <input v-model="limits[period]" type="number" min="1" step="0.01" :placeholder="$t('No limit')"
                                   class="mt-1 w-full rounded-vp border border-vp-border bg-vp-surface-2 py-2 px-3 text-vp-text focus:border-vp-primary focus:ring-0" />
                            <span v-if="state.deposit_limits[period].pending" class="mt-1 block text-xs text-vp-warning">
                                {{ $t('Pending') }}: {{ state.deposit_limits[period].pending === 'none' ? $t('No limit') : state.deposit_limits[period].pending }} · {{ formatDate(state.pending_effective_at) }}
                            </span>
                        </label>
                    </div>
                    <VpButton type="submit" :disabled="busy">{{ $t('Save limits') }}</VpButton>
                </form>

                <!-- Reality check -->
                <div class="rounded-vp border border-vp-border bg-vp-surface p-6 space-y-3">
                    <h3 class="text-lg font-semibold">{{ $t('Reality check') }}</h3>
                    <p class="text-sm text-vp-muted">{{ $t('Get a reminder of how long you have been playing.') }}</p>
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="$t('Reality check')">
                        <VpChip v-for="m in [null, 15, 30, 60, 120]" :key="String(m)" :active="state.reality_check_minutes === m" @click="setReality(m)">
                            {{ m === null ? $t('Off') : `${m} min` }}
                        </VpChip>
                    </div>
                </div>

                <!-- Take a break / self-exclusion -->
                <div class="rounded-vp border border-vp-border bg-vp-surface p-6 space-y-3">
                    <h3 class="text-lg font-semibold">{{ $t('Take a break or self-exclude') }}</h3>
                    <p class="text-sm text-vp-muted">{{ $t('During the period you cannot deposit or play. It cannot be shortened or cancelled.') }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <select v-model="exclusion" class="rounded-vp border border-vp-border bg-vp-surface-2 py-2 text-vp-text focus:border-vp-primary focus:ring-0">
                            <option v-for="o in state.exclusion_options" :key="o" :value="o">{{ exclusionLabels[o] ?? o }}</option>
                        </select>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="confirmExclusion" type="checkbox" class="rounded" /> {{ $t('I understand this cannot be undone early') }}</label>
                        <VpButton variant="outline" :disabled="!confirmExclusion || busy" @click="exclude">{{ $t('Confirm') }}</VpButton>
                    </div>
                </div>
            </template>
        </section>
    </LegalPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { trans } from 'laravel-vue-i18n';
import HttpApi from '@/Services/HttpApi.js';
import { useAuthStore } from '@/Stores/Auth.js';
import LegalPage from '@/Pages/Legal/LegalPage.vue';
import VpButton from '@/Components/DS/VpButton.vue';
import VpChip from '@/Components/DS/VpChip.vue';

const auth = useAuthStore();
const isAuth = computed(() => auth.isAuth);
const exclusionLabels = computed(() => ({
    '24h': trans('24 hours'), '7d': trans('7 days'), '30d': trans('30 days'),
    '6m': trans('6 months'), '1y': trans('1 year'), permanent: trans('Permanently'),
}));
const toast = useToast();
const state = ref(null);
const busy = ref(false);
const limits = reactive({ daily: '', weekly: '', monthly: '' });
const exclusion = ref('24h');
const confirmExclusion = ref(false);
const formatDate = (iso) => iso ? new Date(iso).toLocaleString(document.documentElement.lang || 'en') : '';

function apply(data) {
    state.value = data;
    for (const p of ['daily', 'weekly', 'monthly']) limits[p] = data.deposit_limits[p].current ?? '';
}
async function call(fn, okMessage) {
    busy.value = true;
    try { apply((await fn()).data); if (okMessage) toast.success(trans(okMessage)); }
    catch (e) { toast.error(e?.response?.data?.message || trans('Something went wrong')); }
    busy.value = false;
}
const saveLimits = () => call(() => HttpApi.put('profile/responsible-gaming/limits', Object.fromEntries(Object.entries(limits).map(([k, v]) => [k, v === '' ? null : v]))), 'Settings saved');
const setReality = (m) => call(() => HttpApi.put('profile/responsible-gaming/reality-check', { minutes: m }), 'Settings saved');
const exclude = () => call(() => HttpApi.post('profile/responsible-gaming/exclude', { duration: exclusion.value, confirm: true }), 'Your account is now self-excluded.');

onMounted(() => { if (isAuth.value) call(() => HttpApi.get('profile/responsible-gaming')); });
</script>
