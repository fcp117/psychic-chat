<script setup>
import { computed } from 'vue';
const props = defineProps({ links: { type: Object, default: () => ({}) } });
const visible = computed(() => ['facebook', 'instagram'].filter(platform => props.links[platform]));
</script>
<template>
    <nav v-if="visible.length" aria-label="Follow Intuition Island" class="mt-5 flex items-center gap-3">
        <a v-for="platform in visible" :key="platform" :href="links[platform]" target="_blank" rel="noopener noreferrer" :aria-label="`${platform === 'facebook' ? 'Facebook' : 'Instagram'} (opens in a new tab)`" :title="platform === 'facebook' ? 'Facebook' : 'Instagram'" class="flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface/80 text-accent-text transition hover:bg-accent-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
            <svg v-if="platform === 'facebook'" viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.413c0-3.026 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.972h-1.513c-1.491 0-1.956.931-1.956 1.887v2.263h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
            <svg v-else viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
        </a>
    </nav>
</template>
