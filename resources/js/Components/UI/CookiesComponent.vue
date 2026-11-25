<template>
    <div v-if="!cookieAccepted" class="flex justify-between items-center fixed left-0 md:left-10 bottom-10 z-[999] p-4 dark:bg-gray-900 shadow-lg">
        <p>{{ $t('We use cookies to give you the best experience.') }}</p>
        <button @click="acceptCookie" class="ui-button-blue ml-3">{{ $t('Accept') }}</button>
    </div>
</template>

<script>
    export default {
        props: {

        },
        data() {
            return {
                cookieAccepted: localStorage.getItem('cookieAccepted') === 'true',
            };
        },
        methods: {
            acceptCookie() {
                localStorage.setItem('cookieAccepted', 'true');
                this.cookieAccepted = true;
            },
        },
        watch: {

        }
    };
</script>
