<template>
    <button type="button" :aria-pressed="active"
            :class="['shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-vp-primary/60',
                     active ? 'bg-vp-primary text-vp-primary-contrast border-vp-primary' : 'bg-vp-surface text-vp-muted border-vp-border hover:text-vp-text hover:border-vp-muted']">
        <slot />
    </button>
</template>

<script setup>
defineProps({ active: { type: Boolean, default: false } });
</script>
