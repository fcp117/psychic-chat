<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({ directory: { type: Boolean, default: false }, balance: { type: Number, default: null } });
const page = usePage();
const credits = computed(() => props.balance ?? page.props.auth?.user?.available_credits ?? 0);
const refreshing = ref(false);
const refresh = () => {
    if (refreshing.value) return;
    refreshing.value = true;
    router.reload({ only: [props.directory ? 'psychics' : 'sessions'], onFinish: () => { refreshing.value = false; } });
};
</script>

<template>
    <header class="shrink-0 border-b border-border bg-surface/80 px-4 pb-3 pt-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="font-serif text-xl leading-tight text-content">{{ directory ? 'Spiritual Coaches' : 'Messages' }}</h1>
                <Link v-if="page.props.auth?.user?.role === 'user'" :href="route('credits')" class="rounded-full border border-border bg-accent-soft px-2.5 py-1 text-xs font-semibold text-accent-text hover:bg-surface-hover" title="View your credits">{{ Number(credits).toLocaleString(undefined, { maximumFractionDigits: 2 }) }} credits left</Link>
            </div>
            <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-accent-text transition hover:bg-accent-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-50" :disabled="refreshing" :aria-busy="refreshing" :aria-label="directory ? 'Refresh spiritual coaches' : 'Refresh conversations'" :title="directory ? 'Refresh spiritual coaches' : 'Refresh conversations'" @click="refresh">
                <svg class="h-4 w-4" :class="{ 'animate-spin motion-reduce:animate-none': refreshing }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5" /><path d="M6.1 7a7 7 0 0 1 11.6-1L20 9M4 15l2.3 3A7 7 0 0 0 18 17" /></svg>
            </button>
        </div>
        <nav aria-label="Chat options" class="mt-1 grid grid-cols-[auto_minmax(0,1fr)] gap-1 rounded-2xl border border-border bg-page p-1">
            <Link :href="route('chat.index')" :aria-current="route().current('chat.*') ? 'page' : undefined" class="flex min-h-11 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition" :class="route().current('chat.*') ? 'bg-accent-soft text-accent-text shadow-sm' : 'text-muted hover:bg-surface-hover'">Chats</Link>
            <Link :href="route('psychics.index')" :aria-current="directory ? 'page' : undefined" :class="directory ? 'bg-accent-soft text-accent-text shadow-sm' : 'text-muted hover:bg-surface-hover'" class="flex min-h-11 items-center justify-center gap-2 rounded-xl px-2 py-2.5 text-center text-xs font-semibold leading-5 transition"><span aria-hidden="true" class="text-accent-text">＋</span>Find a Spiritual Coach</Link>
        </nav>
        <div class="mt-3 flex items-start gap-2">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-accent-text" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
            <p class="text-xs leading-5 text-muted">Billing starts only after coach acceptance.</p>
        </div>
    </header>
</template>
