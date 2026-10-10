<script setup>
import {Link} from '@inertiajs/vue3';
</script>
<template><section class="space-y-3"><h2 class="text-lg font-medium">Privacy & account closure</h2><p class="text-sm text-muted">Request transcript deletion or account closure with admin review. Download your chats first. Closure forfeits unused minutes; financial records, support messages, and private coach notes remain outside this deletion workflow.</p><Link :href="route('privacy.requests')" class="inline-block rounded-full border border-border px-4 py-2 text-sm text-accent-text">Manage privacy requests</Link></section></template>
