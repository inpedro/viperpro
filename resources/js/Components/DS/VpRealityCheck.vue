<template>
    <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4" role="alertdialog" aria-modal="true" :aria-label="$t('Reality check')">
        <div class="w-full max-w-md rounded-vp border border-vp-border bg-vp-surface p-6 text-vp-text shadow-xl">
            <h2 class="vp-heading text-xl font-bold mb-2">{{ $t('Reality check') }}</h2>
            <p class="text-vp-muted mb-6">{{ $t('You have been playing for :minutes minutes.', { minutes: elapsed }) }}</p>
            <div class="flex flex-wrap gap-3">
                <VpButton @click="open = false">{{ $t('Continue playing') }}</VpButton>
                <VpButton variant="outline" :to="{ name: 'responsibleGaming' }" @click="open = false">{{ $t('Responsible gaming') }}</VpButton>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import HttpApi from '@/Services/HttpApi.js';
import { useAuthStore } from '@/Stores/Auth.js';
import VpButton from './VpButton.vue';

const open = ref(false);
const elapsed = ref(0);
let timer = null;
const startedAt = Date.now();

onMounted(async () => {
    if (!useAuthStore().isAuth) return;
    try {
        const { data } = await HttpApi.get('profile/responsible-gaming');
        const minutes = data.reality_check_minutes;
        if (!minutes) return;
        timer = setInterval(() => {
            elapsed.value = Math.round((Date.now() - startedAt) / 60000);
            open.value = true;
        }, minutes * 60000);
    } catch (e) { /* not logged in / unavailable */ }
});
onBeforeUnmount(() => clearInterval(timer));
</script>
