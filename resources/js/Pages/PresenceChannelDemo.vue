<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const onlineCounselors = ref([]);

onMounted(() => {
    // Join the presence channel
    window.Echo?.join('counselors.online')
        // .here() fires immediately with an array of everyone currently in the channel
        .here((users) => {
            onlineCounselors.value = users;
        })
        // .joining() fires when a new counselor logs in
        .joining((user) => {
            onlineCounselors.value.push(user);
        })
        // .leaving() fires when a counselor closes their browser or logs out
        .leaving((user) => {
            onlineCounselors.value = onlineCounselors.value.filter(u => u.id !== user.id);
        });
});

// Clean up the listener when the component unmounts to prevent memory leaks
onUnmounted(() => {
    window.Echo?.leave('counselors.online');
});
</script>

<template>
    <Head title="Online community" />
    <AuthenticatedLayout>
        <section class="mx-auto max-w-5xl px-5 py-12 sm:px-8">
            <p class="text-xs uppercase tracking-[0.25em] text-accent-text">Live presence</p>
            <h1 class="mt-3 font-serif text-4xl">A space to connect.</h1>
            <p class="mt-4 text-muted">See who is connected to the community right now.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <article v-for="person in onlineCounselors" :key="person.id" class="flex items-center gap-4 rounded-2xl border border-border bg-surface p-6">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent-soft font-serif text-xl text-accent-text" aria-hidden="true">{{ Array.from(person.name || '?')[0] }}</span>
                    <div class="min-w-0"><h2 class="break-words font-semibold">{{ person.name }}</h2><p class="mt-1 text-xs text-success">● Online <span class="capitalize text-muted">· {{ person.role }}</span></p></div>
                </article>
            </div>
            <p v-if="!onlineCounselors.length" class="mt-8 rounded-2xl border border-border bg-surface p-8 text-muted">No connected members to show yet. The list updates automatically.</p>
            <Link :href="route('chat.index')" class="action mt-8 inline-block">Go to your conversations</Link>
        </section>
    </AuthenticatedLayout>
</template>