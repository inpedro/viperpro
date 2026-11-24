<template>
    <div class="flex items-end justify-between gap-4 mb-4">
        <div class="min-w-0">
            <h2 class="vp-heading text-lg md:text-xl font-bold text-vp-text truncate">
                <i v-if="icon" :class="[icon, 'mr-2 text-vp-primary']" aria-hidden="true"></i>{{ title }}
            </h2>
            <p v-if="subtitle" class="text-sm text-vp-muted mt-0.5">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0"><slot /></div>
    </div>
</template>

<script setup>
defineProps({ title: String, subtitle: String, icon: String });
</script>
