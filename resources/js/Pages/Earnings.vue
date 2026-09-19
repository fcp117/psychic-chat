<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ totalUnits: [Number, String], readings: Object });
const credits = units => (Number(units)/3600).toLocaleString(undefined,{maximumFractionDigits:4});
</script>
<template><Head title="Earnings" /><AuthenticatedLayout><section class="mx-auto max-w-5xl px-6 py-12"><p class="text-xs uppercase tracking-widest text-accent-text">Your readings</p><h1 class="mt-3 font-serif text-4xl">Earnings</h1><div class="my-8 rounded-2xl bg-accent-soft p-8"><p class="text-sm text-accent-text">Net credits earned</p><p class="mt-3 font-serif text-5xl">{{ credits(totalUnits) }}</p><p class="mt-4 text-sm text-muted">Reading charges minus refunds. Credits are not a cash balance; payouts and currency conversion are not configured.</p></div><h2 class="mb-5 font-serif text-2xl">Earnings by conversation</h2><article v-for="s in readings.data" :key="s.chat_session_id" class="mb-4 flex justify-between gap-4 rounded-xl border border-border bg-surface p-6"><div><h3 class="font-semibold">{{ s.client_name || 'Closed account' }}</h3><p class="text-sm text-muted">Reading #{{ s.chat_session_id }}</p></div><p class="text-accent-text">{{ credits(s.earning_units) }} credits</p></article><p v-if="!readings.data.length" class="text-muted">Your first paid reading will appear here after billable time is recorded.</p><PageLinks :data="readings" /></section></AuthenticatedLayout></template>
