<template>
    <BaseLayout>
        <div class="mx-auto mt-20 w-full max-w-6xl px-4">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <aside class="hidden md:block">
                    <WalletSideMenu />
                </aside>

                <section class="md:col-span-2 space-y-6" :aria-busy="loading">
                    <!-- Balances -->
                    <div v-if="wallet" class="rounded-vp border border-vp-border bg-vp-surface p-5">
                        <p class="text-sm text-vp-muted">{{ $t('Total balance') }}</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-vp-text">{{ money(wallet.total_balance) }}</p>

                        <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div v-for="item in balances" :key="item.key" class="rounded-vp bg-vp-surface-2 p-3">
                                <dt class="text-xs text-vp-muted">{{ item.label }}</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-vp-text">{{ money(wallet[item.key]) }}</dd>
                                <dd class="mt-1 text-xs text-vp-muted">{{ item.hint }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <VpButton to="/profile/deposit">
                                <i class="fa-light fa-money-simple-from-bracket" aria-hidden="true"></i>{{ $t('Deposit') }}
                            </VpButton>
                            <VpButton to="/profile/withdraw" variant="outline">
                                <i class="fa-sharp fa-light fa-money-bill-transfer" aria-hidden="true"></i>{{ $t('Withdraw') }}
                            </VpButton>
                        </div>
                    </div>

                    <!-- Wagering requirements: shown as the amount still to wager, never as a made-up percentage. -->
                    <div v-if="wallet && wagering.length" class="rounded-vp border border-vp-border bg-vp-surface p-5">
                        <h2 class="text-base font-semibold text-vp-text">{{ $t('Wagering requirements') }}</h2>
                        <p class="mt-1 text-sm text-vp-muted">{{ $t('Amount you still need to wager before the related funds can be withdrawn.') }}</p>
                        <ul class="mt-4 space-y-3">
                            <li v-for="item in wagering" :key="item.key" class="flex items-center justify-between rounded-vp bg-vp-surface-2 px-4 py-3">
                                <span class="text-sm text-vp-text">{{ item.label }}</span>
                                <span class="font-semibold tabular-nums" :class="Number(wallet[item.key]) > 0 ? 'text-vp-primary' : 'text-vp-muted'">
                                    {{ Number(wallet[item.key]) > 0 ? money(wallet[item.key]) : $t('Completed') }}
                                </span>
                            </li>
                        </ul>
                        <p v-if="Number(setting?.rollover_protection) > 0" class="mt-3 text-xs text-vp-muted">
                            {{ $t('Bonus rollover protection: at least :count bets are required to clear the bonus rollover.', { count: setting.rollover_protection }) }}
                        </p>
                        <RouterLink :to="{ name: 'legalPage', params: { slug: 'bonus' } }" class="mt-3 inline-block text-xs text-vp-primary hover:underline">
                            {{ $t('Bonus terms') }}
                        </RouterLink>
                    </div>

                    <!-- Wallets (one per currency) -->
                    <div v-if="mywallets && mywallets.length > 1" class="rounded-vp border border-vp-border bg-vp-surface p-5">
                        <h2 class="text-base font-semibold text-vp-text">{{ $t('My Wallets') }}</h2>
                        <ul class="mt-4 divide-y divide-vp-border">
                            <li v-for="item in mywallets" :key="item.id" class="flex items-center justify-between py-3">
                                <div>
                                    <p class="font-semibold tabular-nums text-vp-text">{{ money(item.total_balance, item.currency) }}</p>
                                    <p class="text-xs text-vp-muted">{{ item.currency }} · {{ $t('Bonus') }} {{ money(item.balance_bonus, item.currency) }}</p>
                                </div>
                                <VpChip v-if="item.active === 1" :active="true">{{ $t('Active') }}</VpChip>
                                <VpButton v-else size="sm" variant="subtle" :disabled="loading" @click="setWallet(item.id)">{{ $t('Use this wallet') }}</VpButton>
                            </li>
                        </ul>
                    </div>

                    <div v-if="loading && !wallet" class="h-48 animate-pulse rounded-vp bg-vp-surface" role="status">
                        <span class="sr-only">{{ $t('Loading') }}...</span>
                    </div>
                </section>
            </div>
        </div>
    </BaseLayout>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { trans } from 'laravel-vue-i18n';
import { useToast } from 'vue-toastification';
import BaseLayout from '@/Layouts/BaseLayout.vue';
import WalletSideMenu from '@/Pages/Profile/Components/WalletSideMenu.vue';
import VpButton from '@/Components/DS/VpButton.vue';
import VpChip from '@/Components/DS/VpChip.vue';
import HttpApi from '@/Services/HttpApi.js';
import { useSettingStore } from '@/Stores/SettingStore.js';

const toast = useToast();
const appState = getCurrentInstance()?.appContext.config.globalProperties.state;
const setting = computed(() => useSettingStore().setting);
const wallet = ref(null);
const mywallets = ref(null);
const loading = ref(true);

const balances = computed(() => [
    { key: 'balance', label: trans('Cash balance'), hint: trans('Available to play') },
    { key: 'balance_withdrawal', label: trans('Withdrawable'), hint: trans('Can be withdrawn now') },
    { key: 'balance_bonus', label: trans('Bonus balance'), hint: trans('Subject to wagering requirements') },
]);

const wagering = computed(() => {
    if (!setting.value || setting.value.disable_rollover) return [];
    return [
        { key: 'balance_deposit_rollover', label: trans('Deposit wagering') },
        { key: 'balance_bonus_rollover', label: trans('Bonus wagering') },
    ];
});

function money(value, currency) {
    return appState.currencyFormat(parseFloat(value || 0), currency || wallet.value?.currency);
}

function showErrors(error) {
    try {
        Object.values(JSON.parse(error.request.responseText)).forEach((v) => toast.error(`${v}`));
    } catch {
        toast.error(trans('Something went wrong'));
    }
}

async function load() {
    loading.value = true;
    try {
        const [w, list] = await Promise.all([HttpApi.get('profile/wallet'), HttpApi.get('profile/mywallet')]);
        wallet.value = w.data.wallet;
        mywallets.value = list.data.wallets;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
}

async function setWallet(id) {
    loading.value = true;
    try {
        await HttpApi.post(`profile/mywallet/${id}`, {});
        window.location.reload();
    } catch (e) {
        showErrors(e);
        loading.value = false;
    }
}

onMounted(load);
</script>
