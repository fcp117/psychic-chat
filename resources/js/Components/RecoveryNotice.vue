<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const page=usePage(),networkMessage=ref(''),dismissed=ref(false),offline=ref(!navigator.onLine),loading=ref(false);
const message=computed(()=>networkMessage.value || page.props.flash?.error || page.props.errors?.request || page.props.flash?.success);
const isError=computed(()=>Boolean(networkMessage.value||page.props.flash?.error||page.props.errors?.request));
watch(message,()=>dismissed.value=false);
let removers=[];
const updateOnline=()=>{offline.value=!navigator.onLine;};
const appError=()=>{networkMessage.value='This page encountered a problem. Reload to recover. Any unsaved form changes may be lost.';dismissed.value=false;};
onMounted(()=>{
 window.addEventListener('online',updateOnline);window.addEventListener('offline',updateOnline);window.addEventListener('psychic:ui-error',appError);
 removers=[router.on('start',()=>loading.value=true),router.on('finish',()=>loading.value=false),router.on('success',()=>networkMessage.value=''),router.on('exception',event=>{event.preventDefault();networkMessage.value='We could not reach the website. Check your connection and retry. Your form is still here.';dismissed.value=false;}),router.on('invalid',event=>{event.preventDefault();networkMessage.value=event.detail.response.status===419?'Your session expired. Reload before trying again.':'The website returned an unexpected response. Reload to reconnect.';dismissed.value=false;})];
});
onUnmounted(()=>{removers.forEach(remove=>remove());window.removeEventListener('online',updateOnline);window.removeEventListener('offline',updateOnline);window.removeEventListener('psychic:ui-error',appError);});
const reload=()=>window.location.reload();
</script>
<template><div v-if="loading" role="status" class="fixed left-1/2 top-2 z-[100] -translate-x-1/2 rounded-full border border-border bg-surface px-4 py-2 text-xs text-muted shadow">Loading…</div><div v-if="offline" role="alert" class="fixed inset-x-0 top-0 z-[110] bg-accent-soft px-4 py-3 text-center text-sm text-accent-text">You’re offline. Reconnect before sending messages or making changes.</div><aside v-if="message&&!dismissed" :role="isError?'alert':'status'" class="fixed bottom-4 left-4 z-[100] w-[min(26rem,calc(100vw-2rem))] rounded-2xl border border-border bg-surface p-4 shadow-xl"><div class="flex items-start gap-4"><p class="flex-1 text-sm leading-6" :class="isError?'text-error':'text-success'">{{ message }}</p><button type="button" class="px-2 text-muted" aria-label="Dismiss notice" @click="dismissed=true">×</button></div><button v-if="isError" class="mt-2 text-xs text-accent-text underline" @click="reload">Reload page</button></aside></template>
