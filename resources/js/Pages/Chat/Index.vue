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
</script>
<template>
    <Head title="Chat" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-6 py-14">
            <p class="text-xs uppercase tracking-[0.25em] text-accent-text">Your conversations</p>
            <h1 class="mt-3 font-serif text-4xl text-content">A space to connect.</h1>
            <p class="mt-4 text-muted">Continue a conversation, or choose Find Psychic to search for someone specific.</p>
            <ChatTabs /><div class="mt-5 flex justify-between gap-3"><p class="text-sm text-muted">Pending requests wait for counselor acceptance before billing starts.</p><button type="button" @click="router.reload({ only: ['sessions'] })" class="text-sm text-accent-text underline">Refresh conversations</button></div>
            <div v-if="sessions.data.length" class="mt-10 space-y-4">
                <article v-for="session in sessions.data" :key="session.id" class="flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-border bg-surface p-6">
                    <div><p class="text-xs capitalize text-accent-text">{{ session.status === 'active' ? 'Active' : session.status === 'pending' ? 'Request pending' : 'Inactive' }}</p><h2 class="mt-2 text-lg font-semibold text-content">{{ partner(session)?.name || 'Your reading' }}</h2></div>
                    <Link :href="route('chat.room', session.conversation_id)" class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary-hover">{{ session.status === 'pending' ? (user.id === session.counselor_id ? 'Review request' : 'Waiting for acceptance') : session.status === 'active' ? 'Resume chat' : 'Open conversation' }} →</Link>
                </article>
                <div class="flex justify-between pt-4"><Link v-if="sessions.prev_page_url" :href="sessions.prev_page_url" class="text-accent-text">← Previous</Link><Link v-if="sessions.next_page_url" :href="sessions.next_page_url" class="ml-auto text-accent-text">Next →</Link></div>
            </div>
            <div v-else class="mt-10 rounded-2xl border border-border bg-surface p-10"><h2 class="font-serif text-2xl">Your conversations will appear here.</h2><p class="mt-3 text-muted">You don’t have a reading yet. Choose Find Psychic to browse counselors and open a conversation.</p></div>
        </div>
    </AuthenticatedLayout>
</template>
