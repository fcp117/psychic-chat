<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ data: Object });
</script>
<template><div v-if="data && (data.prev_page_url || data.next_page_url)" class="mt-6 flex justify-between gap-4 text-sm text-accent-text"><Link v-if="data.prev_page_url" :href="data.prev_page_url">← Previous</Link><span class="text-muted">Page {{ data.current_page }}</span><Link v-if="data.next_page_url" :href="data.next_page_url">Next →</Link></div></template>
