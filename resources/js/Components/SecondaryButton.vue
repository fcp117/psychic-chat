<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-border bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-content shadow-sm transition duration-150 ease-in-out hover:bg-surface-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-page disabled:opacity-25"
    >
        <slot />
    </button>
</template>
