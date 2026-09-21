<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LoginLayout from '@/Layouts/LoginLayout.vue';
const props=defineProps({status:Number});
const messages={403:['This space isn’t available','Your account does not have access to this page or action.'],404:['We couldn’t find that page','The link may have changed or this item is no longer available.'],419:['Your session has expired','Reload the page and sign in again before continuing.'],429:['Let’s pause for a moment','Too many requests were received. Please wait a minute before trying again.'],500:['Something went wrong','We couldn’t complete your request. If you were making a payment, check its status before starting another.'],503:['We’ll be back shortly','The service is temporarily unavailable. Please try again shortly.']};
const message=computed(()=>messages[props.status]||messages[500]);
const reload=()=>window.location.reload();
</script>
<template><Head :title="message[0]" /><LoginLayout :title="message[0]" :description="message[1]"><p class="mb-6 font-mono text-sm text-muted">Error {{ status }}</p><div class="flex flex-wrap gap-3"><Link href="/" class="action">Go to Home</Link><button v-if="status!==403 && status!==404" class="rounded-full border border-border px-5 py-3 text-sm" @click="reload">Reload page</button></div><Link href="/login" class="mt-6 inline-block text-sm text-accent-text underline">Return to login</Link></LoginLayout></template>
