<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
const page = usePage();
</script>

<template>
    <Head title="About Intuition Island" />
    <div class="min-h-screen bg-page text-content">
        <header class="border-b border-border bg-surface"><nav class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4 sm:px-8" aria-label="Main navigation"><Link :href="route('home')" class="flex items-center gap-2"><ApplicationLogo class="h-8 w-8 text-primary" /><span class="font-serif text-lg">Intuition Island</span></Link><Link :href="route(page.props.auth?.user ? 'home' : 'login')" class="text-sm text-accent-text underline">{{ page.props.auth?.user ? 'Return home' : 'Log in' }}</Link></nav></header>
        <main class="mx-auto max-w-4xl px-5 py-14 sm:px-8"><p class="text-xs font-semibold uppercase tracking-[0.28em] text-accent-text">About us</p><h1 class="mt-4 font-serif text-4xl leading-tight sm:text-5xl">A space for reflection and connection.</h1><div class="mt-10 space-y-7 rounded-3xl border border-border bg-surface p-7 leading-7 text-muted sm:p-10"><p>Intuition Island helps adults find and connect with independent counselors for private online conversations. Members can explore counselor profiles, understand the listed rate, and request a chat when they are ready.</p><p>We believe meaningful conversations deserve clarity. Rates are shown before a request is made, and a chat begins only when the counselor accepts. Intuition Island also offers an educational library guide for general concepts; it is not a substitute for a live counselor, professional care, or emergency support.</p><p>Every person’s experience is personal. We encourage members to use the platform thoughtfully, protect their private information, and seek qualified local help for health, legal, financial, or emergency concerns.</p></div></main>
        <footer class="border-t border-border px-5 py-6 text-center text-xs text-muted">Intuition Island · Space for reflection and connection.</footer>
    </div>
</template>
