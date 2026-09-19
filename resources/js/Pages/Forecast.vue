<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head } from '@inertiajs/vue3';
defineProps({ forecasts: Object });
</script>
<template><Head title="Forecast" /><AuthenticatedLayout><section class="mx-auto max-w-5xl px-6 py-12"><p class="text-xs uppercase tracking-widest text-accent-text">A little perspective</p><h1 class="mt-3 font-serif text-4xl">Forecast</h1><article v-for="f in forecasts.data" :key="f.id" class="mt-7 rounded-2xl border border-border bg-surface p-8"><h2 class="font-serif text-3xl">{{ f.title }}</h2><p class="mt-2 text-xs text-muted">{{ f.published_at.slice(0,10) }}</p><p class="mt-5 whitespace-pre-wrap leading-7 text-content">{{ f.body }}</p></article><div v-if="!forecasts.data.length" class="mt-8 rounded-2xl border border-border bg-surface p-12 text-center"><p class="text-4xl text-primary" aria-hidden="true">☾</p><h2 class="mt-5 font-serif text-3xl">A moment to look ahead.</h2><p class="mt-4 text-muted">No forecasts have been published yet.</p></div><PageLinks :data="forecasts" /></section></AuthenticatedLayout></template>
