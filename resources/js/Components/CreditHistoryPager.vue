<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ data: Object, label: String });
</script>
<template>
    <nav :aria-label="label" class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs">
        <span class="text-muted">{{ data.from || 0 }}–{{ data.to || 0 }} of {{ data.total }} · Page {{ data.current_page }} / {{ data.last_page }}</span>
        <div class="flex gap-2">
            <Link v-if="data.prev_page_url" :href="data.prev_page_url" preserve-scroll preserve-state class="rounded-lg border border-border px-3 py-2 text-accent-text">Previous</Link>
            <span v-else class="rounded-lg border border-border px-3 py-2 text-muted opacity-50" aria-disabled="true">Previous</span>
            <Link v-if="data.next_page_url" :href="data.next_page_url" preserve-scroll preserve-state class="rounded-lg border border-border px-3 py-2 text-accent-text">Next</Link>
            <span v-else class="rounded-lg border border-border px-3 py-2 text-muted opacity-50" aria-disabled="true">Next</span>
        </div>
    </nav>
</template>
