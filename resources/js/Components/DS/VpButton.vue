<template>
    <component :is="to ? 'RouterLink' : 'button'" :to="to" :type="to ? undefined : type"
               :class="['inline-flex items-center justify-center gap-2 font-semibold transition-colors rounded-vp focus:outline-none focus-visible:ring-2 focus-visible:ring-vp-primary/60 disabled:opacity-50 disabled:cursor-not-allowed', sizes[size], variants[variant]]">
        <slot />
    </component>
</template>

<script setup>
defineProps({
    variant: { type: String, default: 'primary' }, // primary | subtle | ghost | outline
    size: { type: String, default: 'md' },          // sm | md | lg
    to: { type: [String, Object], default: null },
    type: { type: String, default: 'button' },
});

const sizes = { sm: 'px-3 py-1.5 text-xs', md: 'px-4 py-2 text-sm', lg: 'px-6 py-3 text-base' };
const variants = {
    primary: 'bg-vp-primary text-vp-primary-contrast hover:bg-vp-primary/90',
    subtle: 'bg-vp-surface-2 text-vp-text hover:bg-vp-border',
    ghost: 'bg-transparent text-vp-muted hover:text-vp-text hover:bg-vp-surface-2',
    outline: 'border border-vp-border text-vp-text hover:border-vp-primary hover:text-vp-primary',
};
</script>
