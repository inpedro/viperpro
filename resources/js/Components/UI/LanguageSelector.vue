<template>
    <button @click.prevent="toggleModal" class="text-[28px] text-gray-500 dark:text-gray-500 mr-3 mt-1" :aria-label="$t('Select language')">
        <i class="fa-light fa-earth-americas"></i>
    </button>

    <div id="drawer-language" class="fixed top-0 right-0 z-40 h-screen p-4 overflow-y-auto transition-transform translate-x-full bg-white w-80 dark:bg-gray-800" tabindex="-1">
        <h5 class="inline-flex items-center mb-4 text-base font-semibold text-gray-500 dark:text-gray-400">
            {{ $t('Select language') }}
        </h5>
        <button @click.prevent="toggleModal" type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 absolute top-2.5 right-2.5 inline-flex items-center justify-center dark:hover:bg-gray-600 dark:hover:text-white">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
            <span class="sr-only">{{ $t('Close') }}</span>
        </button>

        <!-- Languages are listed by their own name (no flags: a flag is a country, not a language). -->
        <ul class="mt-6 space-y-2" role="radiogroup" :aria-label="$t('Select language')">
            <li v-for="locale in locales" :key="locale.code">
                <label class="flex items-center justify-between rounded-lg border px-4 py-3 cursor-pointer transition-colors"
                       :class="language === locale.code ? 'border-primary-500 bg-primary-50 dark:bg-gray-700' : 'border-gray-200 dark:border-gray-600'">
                    <span :lang="locale.html_lang" :dir="locale.dir">
                        <span class="block font-medium text-gray-900 dark:text-white">{{ locale.native }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ locale.name }}</span>
                    </span>
                    <input type="radio" class="sr-only" name="language_selection" :value="locale.code" v-model="language" />
                    <i v-if="language === locale.code" class="fa-solid fa-check text-primary-500" aria-hidden="true"></i>
                </label>
            </li>
        </ul>
    </div>
</template>

<script>
    import { initFlowbite, Drawer } from 'flowbite';
    import HttpApi from "@/Services/HttpApi.js";
    import { loadLanguageAsync } from 'laravel-vue-i18n';

    export default {
        data() {
            return {
                drawer: null,
                language: null,
                locales: [],
                ready: false,
            };
        },
        mounted() {
            initFlowbite();
            const el = document.getElementById('drawer-language');
            if (el) {
                this.drawer = new Drawer(el, {
                    placement: 'right',
                    backdrop: true,
                    bodyScrolling: false,
                    backdropClasses: 'bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-30',
                });
            }
            this.load();
        },
        methods: {
            toggleModal() {
                this.drawer?.toggle();
            },
            async load() {
                try {
                    const { data } = await HttpApi.get('/settings/locales');
                    this.locales = data.enabled;

                    // ?lang=xx is honoured only if the casino offers that locale.
                    const requested = new URLSearchParams(window.location.search).get('lang');
                    const normalized = requested ? requested.replace('-', '_') : null;
                    const match = this.locales.find(l => l.code.toLowerCase() === (normalized || '').toLowerCase());

                    this.apply(match ? match.code : data.current, Boolean(match));
                } catch (e) {
                    // keep the server-rendered locale
                }
                this.ready = true;
            },
            apply(code, persist) {
                const locale = this.locales.find(l => l.code === code);
                if (!locale) {
                    return;
                }
                this.language = code;
                loadLanguageAsync(code);
                document.documentElement.lang = locale.html_lang;
                document.documentElement.dir = locale.dir;
                if (persist) {
                    HttpApi.put('/profile/updateLanguage', { language: code }).catch(() => {});
                }
            },
        },
        watch: {
            language(newValue, oldValue) {
                if (this.ready && oldValue !== null && newValue !== oldValue) {
                    this.apply(newValue, true);
                }
            },
        },
    };
</script>
