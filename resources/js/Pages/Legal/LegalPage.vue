<template>
    <BaseLayout>
        <div class="bg-vp-bg text-vp-text min-h-screen">
            <article class="mx-auto max-w-3xl px-4 md:px-6 py-10">
                <div v-if="loading" class="space-y-3" aria-hidden="true">
                    <div class="h-8 w-2/3 rounded-vp bg-vp-surface-2 animate-pulse"></div>
                    <div class="h-4 w-full rounded-vp bg-vp-surface-2 animate-pulse"></div>
                    <div class="h-4 w-5/6 rounded-vp bg-vp-surface-2 animate-pulse"></div>
                </div>
                <template v-else>
                    <h1 class="vp-heading text-3xl font-extrabold mb-2">{{ page.title }}</h1>
                    <p v-if="page.updated_at" class="text-sm text-vp-muted mb-8">{{ $t('Last updated') }}: {{ new Date(page.updated_at).toLocaleDateString(lang) }}</p>
                    <!-- Rendered server-side from Markdown with raw HTML stripped (LegalPage::html). -->
                    <div v-if="page.published" class="vp-prose" v-html="page.html"></div>
                    <div v-else class="rounded-vp border border-vp-border bg-vp-surface p-6 text-vp-muted">
                        {{ $t('This document has not been published by the operator yet.') }}
                    </div>
                </template>
                <slot />
            </article>
        </div>
    </BaseLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import BaseLayout from '@/Layouts/BaseLayout.vue';
import HttpApi from '@/Services/HttpApi.js';

const props = defineProps({ slug: { type: String, default: null } });
const route = useRoute();
const page = ref({});
const loading = ref(true);
const lang = computed(() => document.documentElement.lang || 'en');
const slug = computed(() => props.slug || route.params.slug);

async function load() {
    loading.value = true;
    try {
        page.value = (await HttpApi.get(`legal/${slug.value}`)).data;
    } catch (e) {
        page.value = { published: false, title: '' };
    }
    loading.value = false;
}
watch(slug, load, { immediate: true });
</script>

<style>
.vp-prose h1, .vp-prose h2, .vp-prose h3 { font-family: var(--vp-font-heading); font-weight: 700; margin: 1.5em 0 .5em; }
.vp-prose h2 { font-size: 1.4rem; } .vp-prose h3 { font-size: 1.15rem; }
.vp-prose p, .vp-prose ul, .vp-prose ol { margin: 0 0 1em; line-height: 1.7; color: var(--vp-text); }
.vp-prose ul { list-style: disc; padding-inline-start: 1.5em; } .vp-prose ol { list-style: decimal; padding-inline-start: 1.5em; }
.vp-prose a { color: var(--vp-primary); text-decoration: underline; }
.vp-prose table { width: 100%; border-collapse: collapse; margin-bottom: 1em; } .vp-prose td, .vp-prose th { border: 1px solid var(--vp-border); padding: .4em .6em; }
</style>
