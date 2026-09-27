<script setup>
import ChatTabs from '@/Components/ChatTabs.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
defineProps({ sessions: Object });
const user = usePage().props.auth.user;
let refreshTimer;
onMounted(() => { refreshTimer=setInterval(() => router.reload({only:['sessions']}),10000); });
onUnmounted(() => clearInterval(refreshTimer));
const partner = session => session.client_id === user.id ? session.counselor : session.client;
const initials = session => (partner(session)?.name || 'I').split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase();
const statusLabel = status => status === 'active' ? 'Active now' : status === 'pending' ? 'Request pending' : 'Conversation ended';
</script>
<template>
    <Head title="Chat" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7">
            <div class="grid min-h-[calc(100svh-8.5rem)] overflow-hidden rounded-3xl border border-border bg-surface shadow-xl shadow-primary/10 lg:grid-cols-[22rem_minmax(0,1fr)]">
                <aside class="flex min-h-0 flex-col border-b border-border bg-page/60 lg:border-b-0 lg:border-r">
                    <div class="border-b border-border px-5 py-5">
                        <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.22em] text-accent-text">Messages</p><h1 class="mt-1 font-serif text-2xl text-content">Your conversations</h1></div><button type="button" class="rounded-full p-2 text-accent-text hover:bg-accent-soft" aria-label="Refresh conversations" title="Refresh conversations" @click="router.reload({ only: ['sessions'] })">↻</button></div>
                        <ChatTabs />
                    </div>
                    <p class="px-5 py-3 text-xs leading-5 text-muted">Pending requests wait for spiritual advisor acceptance before billing starts.</p>
                    <div v-if="sessions.data.length" class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
                        <Link v-for="session in sessions.data" :key="session.id" :href="route('chat.room', session.conversation_id)" class="group flex items-center gap-3 rounded-2xl px-3 py-3 transition hover:bg-accent-soft focus-visible:bg-accent-soft">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-accent-text">{{ initials(session) }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-content">{{ partner(session)?.name || 'Your reading' }}</span><span class="mt-1 block truncate text-xs" :class="session.status === 'active' ? 'text-success' : 'text-muted'">{{ statusLabel(session.status) }}</span></span>
                            <span class="text-lg text-muted transition group-hover:translate-x-0.5 group-hover:text-accent-text" aria-hidden="true">›</span>
                        </Link>
                        <div class="flex justify-between px-3 pt-3 text-sm"><Link v-if="sessions.prev_page_url" :href="sessions.prev_page_url" class="text-accent-text hover:underline">← Previous</Link><Link v-if="sessions.next_page_url" :href="sessions.next_page_url" class="ml-auto text-accent-text hover:underline">Next →</Link></div>
                    </div>
                    <div v-else class="px-5 pb-6 pt-3"><p class="text-sm font-medium text-content">No conversations yet</p><p class="mt-1 text-sm leading-6 text-muted">Find a spiritual advisor when you’re ready to begin.</p><Link :href="route('psychics.index')" class="mt-4 inline-flex text-sm font-semibold text-accent-text hover:underline">Find a Spiritual Advisor →</Link></div>
                </aside>
                <main class="flex min-h-[25rem] items-center justify-center bg-gradient-to-br from-page via-surface to-accent-soft/40 p-6 sm:p-10">
                    <div class="max-w-md text-center"><span class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-3xl text-accent-text" aria-hidden="true">✦</span><p class="mt-6 text-xs font-semibold uppercase tracking-[0.24em] text-accent-text">Intuition Island chat</p><h2 class="mt-3 font-serif text-3xl text-content">Choose a conversation</h2><p class="mt-4 text-sm leading-7 text-muted">Select a conversation from the left to continue where you left off. New here? Find a spiritual advisor to start a request.</p><Link :href="route('psychics.index')" class="mt-7 inline-flex rounded-full bg-primary px-5 py-3 text-sm font-semibold text-on-primary transition hover:bg-primary-hover">Find a Spiritual Advisor</Link></div>
                </main>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
