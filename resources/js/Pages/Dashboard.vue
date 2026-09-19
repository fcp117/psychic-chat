<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
const paths = [
    { number: '01', title: 'A space to connect', text: 'Return to your conversations and make space for what is on your mind.', label: 'Explore your chats', route: 'chat.index' },
    { number: '02', title: 'A little perspective', text: 'Your future home for forecasts and moments of reflection.', label: 'Visit Forecast', route: 'forecast' },
    { number: '03', title: 'Your reading balance', text: 'See your available credits and stay ready for your next conversation.', label: 'View credits', route: 'credits' },
];
</script>

<template>
    <Head title="Home" />
    <AuthenticatedLayout>
        <section class="psychic-hero relative isolate overflow-hidden">
            <div class="hero-shade absolute inset-0 -z-10"></div>
            <div class="hero-content mx-auto flex max-w-7xl items-center px-5 py-12 sm:px-10 lg:px-8">
                <div class="max-w-xl">
                    <p class="mb-7 text-xs font-semibold uppercase tracking-[0.3em] text-accent-text">A moment for yourself</p>
                    <h1 class="font-serif hero-title leading-[1.1] tracking-tight text-content">Your journey.<br />A new perspective.</h1>
                    <p class="mt-7 max-w-sm text-base leading-7 text-muted">Slow down, explore your questions, and find space for a meaningful conversation.</p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <Link :href="route('chat.index')" class="rounded-full bg-primary px-7 py-3.5 text-sm font-semibold text-on-primary hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4">Go to Chat <span aria-hidden="true" class="ml-3">↗</span></Link>
                        <Link :href="route('forecast')" class="rounded-full border border-border bg-surface px-7 py-3.5 text-sm font-semibold text-content hover:bg-surface-hover">Explore Forecast</Link>
                    </div>
                    <p class="mt-12 text-xs tracking-wide text-muted">Welcome back, {{ $page.props.auth.user.name }}.</p>
                </div>
            </div>
        </section>
        <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="mb-9 flex items-center gap-4"><span class="text-accent-text" aria-hidden="true">✧</span><h2 class="font-serif text-3xl text-content">Make this moment yours</h2></div>
            <div class="grid gap-5 md:grid-cols-3">
                <article v-for="item in paths" :key="item.route" class="rounded-2xl border border-border bg-surface p-7">
                    <p class="mb-8 text-xs tracking-widest text-muted">{{ item.number }} / PSYCHIC CHAT</p>
                    <h3 class="font-serif text-2xl text-content">{{ item.title }}</h3>
                    <p class="mb-7 mt-3 text-sm leading-6 text-muted">{{ item.text }}</p>
                    <Link :href="route(item.route)" class="text-sm font-semibold text-accent-text hover:underline">{{ item.label }} <span aria-hidden="true">→</span></Link>
                </article>
            </div>
        </section>
        <footer class="border-t border-border px-6 py-6 text-center text-xs text-muted">Psychic Chat · Space for reflection and connection.</footer>
    </AuthenticatedLayout>
</template>
