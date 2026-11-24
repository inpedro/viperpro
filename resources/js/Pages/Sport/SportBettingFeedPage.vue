<template>
    <BaseLayout>
        <div class="p-4">
            <div class="bets-header font-bold">
                <div class="flex text-3xl items-center">
                    <i class="fa-light fa-book "></i>
                    <h1 class="ml-2 ">{{ $t('Bets Feed') }}</h1>
                </div>
            </div>

            <!-- Navegação e filtro -->
            <div class="flex justify-between items-center scrollable-container">
                <div class="bets-options flex gap-4 mt-5 scrollable-content">
                    <button class="transition duration-700 bg-green-500 rounded-full px-4 py-2 font-bold text-white">
                        <span>{{ $t('All') }}</span>
                    </button>

                    <button class="hover:bg-gray-300 hover:dark:bg-gray-600/20 transition duration-700 flex bg-gray-200 dark:bg-gray-700/20 rounded-full px-4 py-2 font-bold text-gray-500 dark:text-gray-400 hover:dark:text-white">
                        <i class="fa-solid fa-futbol"></i>
                        <span class="ml-2">{{ $t('Football') }}</span>
                    </button>

                    <button class="hover:bg-gray-300 hover:dark:bg-gray-600/20 transition duration-700 flex bg-gray-200 dark:bg-gray-700/20 rounded-full px-4 py-2 font-bold text-gray-500 dark:text-gray-400 hover:dark:text-white">
                        <i class="fa-solid fa-basketball"></i>
                        <span class="ml-2">{{ $t('Basquetebol') }}</span>
                    </button>

                    <button class="hover:bg-gray-300 hover:dark:bg-gray-600/20 transition duration-700 flex bg-gray-200 dark:bg-gray-700/20 rounded-full px-4 py-2 font-bold text-gray-500 dark:text-gray-400 hover:dark:text-white">
                        <i class="fa-regular fa-volleyball"></i>
                        <span class="ml-2">{{ $t('Volleyball') }}</span>
                    </button>

                    <button class="hover:bg-gray-300 hover:dark:bg-gray-600/20 transition duration-700 flex bg-gray-200 dark:bg-gray-700/20 rounded-full px-4 py-2 font-bold text-gray-500 dark:text-gray-400 hover:dark:text-white">
                        <i class="fa-regular fa-football"></i>
                        <span class="ml-2">{{ $t('American Football') }}</span>
                    </button>

                </div>
                <div class="">

                </div>
            </div>


            <div class=" mt-5 mb-16">
                <div class="relative overflow-x-auto">
                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3 bg-gray-50 dark:bg-gray-800">
                                        {{ $t('Event') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Result') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 bg-gray-50 dark:bg-gray-800">
                                        {{ $t('Odds') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Bet') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Potential gain') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('User') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Previously hardcoded rows with invented users and stakes (fabricated activity, removed).
                                     A real feed must come from settled bets via an API. -->
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">{{ $t('No data to show') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </BaseLayout>
</template>


<script>

import BaseLayout from "@/Layouts/BaseLayout.vue";

export default {
    props: [],
    components: {BaseLayout},
    data() {
        return {
            isLoading: false,
        }
    },
    setup(props) {


        return {};
    },
    computed: {

    },
    mounted() {

    },
    methods: {

    },
    watch: {

    },
};
</script>

<style scoped>

</style>
