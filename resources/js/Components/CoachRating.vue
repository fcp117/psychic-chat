<script setup>
import { computed } from 'vue';
const props = defineProps({ rating: Object });
const count = computed(() => Number(props.rating?.count || 0));
const average = computed(() => Math.max(0, Math.min(5, Number(props.rating?.average || 0))));
</script>

<template>
    <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs" :aria-label="count ? `${average.toFixed(1)} out of 5, ${count} reviews` : 'No ratings yet'">
        <span class="relative inline-block whitespace-nowrap text-base leading-none tracking-[0.08em] text-muted/40" aria-hidden="true">
            ★★★★★
            <span class="absolute inset-y-0 left-0 overflow-hidden text-amber-400" :style="{ width: `${average / 5 * 100}%` }">★★★★★</span>
        </span>
        <span class="text-muted">{{ count ? `${average.toFixed(1)} · ${count} ${count === 1 ? 'review' : 'reviews'}` : 'No ratings yet' }}</span>
    </span>
</template>
