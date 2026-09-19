<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
const page=usePage(), open=ref(false), toggle=ref(null), requests=ref([]), dismissed=ref([]);
const user=computed(() => page.props.auth?.user);
const inRoom=computed(() => page.component === 'Chat/Room');
const visibleRequests=computed(() => requests.value.filter(r => !dismissed.value.includes(r.id)));
let timer, subscribedId, loading=false;
const refresh=async () => { if (user.value?.role !== 'counselor' || loading) return; loading=true; try { requests.value=(await axios.get(route('chat.requests'))).data.requests; } catch { /* Retry on the next interval. */ } finally { loading=false; } };
const subscribe=() => {
    if (subscribedId) window.Echo?.leave(`App.Models.User.${subscribedId}`);
    requests.value=[]; dismissed.value=[]; subscribedId=user.value?.id;
    if (subscribedId && user.value.role==='counselor') { window.Echo?.private(`App.Models.User.${subscribedId}`).listen('.ReadingUpdated',refresh); refresh(); }
};
watch(() => [user.value?.id,user.value?.role],subscribe);
onMounted(() => { subscribe(); timer=setInterval(refresh,10000); });
onUnmounted(() => { clearInterval(timer); if(subscribedId) window.Echo?.leave(`App.Models.User.${subscribedId}`); });
const close=() => { open.value=false; toggle.value?.focus(); };
</script>
<template>
    <aside v-if="user && visibleRequests.length" aria-label="Reading requests" aria-live="polite" class="fixed right-3 top-3 z-40 w-[min(22rem,calc(100vw-1.5rem))] space-y-2 sm:right-5 sm:top-5"><div v-for="item in visibleRequests.slice(0,3)" :key="item.id" class="rounded-2xl border border-border bg-surface p-4 shadow-xl"><div class="flex justify-between gap-4"><p class="text-sm font-semibold">{{ item.name }} requested a reading</p><button class="text-muted" aria-label="Dismiss notification" @click="dismissed.push(item.id)">×</button></div><Link :href="route('chat.room',item.conversationId)" class="mt-2 inline-block text-sm text-accent-text underline" @click="dismissed.push(item.id)">Open conversation to accept →</Link></div></aside>
    <div v-if="user" class="fixed right-3 z-30 sm:right-5" :class="inRoom ? 'bottom-28' : 'bottom-5'" @keydown.esc="close">
        <section v-if="open" id="site-assistant" aria-label="Psychic Chat assistant preview" :style="{maxHeight: inRoom ? 'min(32rem, calc(100dvh - 13rem))' : 'min(32rem, calc(100dvh - 7rem))'}" class="mb-3 flex w-[min(23rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-2xl">
            <header class="flex items-center justify-between gap-3 bg-accent-soft p-4"><div><h2 class="text-sm font-semibold text-content">Psychic Chat assistant</h2><p class="mt-1 text-xs text-muted">Coming soon</p></div><button class="rounded-lg px-2 py-1 text-xl" aria-label="Close assistant" @click="close">×</button></header>
            <div class="min-h-0 flex-1 overflow-y-auto p-5"><div class="mb-4 text-3xl text-accent-text" aria-hidden="true">✧</div><p class="text-sm leading-6 text-content">A little guidance, right here.</p><p class="mt-2 text-sm leading-6 text-muted">This is the future home of your assistant. Chat isn’t available yet.</p></div>
            <div class="border-t border-border p-4"><label for="assistant-preview" class="sr-only">Assistant message (coming soon)</label><input id="assistant-preview" disabled placeholder="Chat is coming soon…" class="field w-full cursor-not-allowed text-sm opacity-60" /></div>
        </section>
        <div class="flex justify-end"><button ref="toggle" type="button" :aria-expanded="open" aria-controls="site-assistant" class="flex h-12 items-center gap-2 rounded-full border border-border bg-primary px-4 text-sm font-semibold text-on-primary shadow-lg" @click="open=!open"><span aria-hidden="true">✧</span><span>{{ open ? 'Close assistant' : 'Assistant' }}</span><span aria-hidden="true">{{ open ? '⌄' : '⌃' }}</span></button></div>
    </div>
</template>
