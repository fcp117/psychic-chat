<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
import PageLinks from '@/Components/PageLinks.vue';
defineProps({incidents:Object});
</script>
<template><Head title="System health" /><AuthenticatedLayout><AdminWorkspace section="health">
 <p class="mb-3 text-sm text-muted">Read-only incident monitoring. No credentials, request bodies, or private messages are shown.</p>
 <div class="overflow-x-auto rounded-xl border border-border"><table class="w-full text-left text-sm"><thead class="bg-page"><tr><th class="p-3">Time (UTC)</th><th class="p-3">Type</th><th class="p-3">Route</th></tr></thead><tbody><tr v-for="i in incidents.data" :key="i.id" class="border-t border-border"><td class="p-3">{{i.created_at}}</td><td class="p-3">{{i.type}}</td><td class="p-3">{{i.method}} {{i.route||'—'}}</td></tr></tbody></table></div><p v-if="!incidents.data.length" class="my-3 text-sm text-muted">No recorded incidents.</p><PageLinks :data="incidents" />
</AdminWorkspace></AuthenticatedLayout></template>
