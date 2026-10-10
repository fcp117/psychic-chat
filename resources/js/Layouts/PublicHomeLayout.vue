<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
const page = usePage();
</script>

<template>
    <div class="app-shell min-h-screen bg-page">
        <nav class="sticky top-0 z-50 px-3 py-4 sm:px-5" aria-label="Main navigation">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 rounded-3xl border border-border bg-surface px-4 py-3 shadow-lg sm:px-6">
                <Link :href="route('home')" class="flex items-center gap-2"><ApplicationLogo class="h-8 w-8 text-primary" /><span class="font-serif text-lg text-content">Intuition Island</span></Link>
                <div class="hidden items-center gap-5 text-sm text-muted lg:flex">
                    <Link :href="route('home')" :class="route().current('home') ? 'rounded-full bg-accent-soft px-4 py-2 text-accent-text' : ''" :aria-current="route().current('home') ? 'page' : undefined">Home</Link>
                    <Link :href="route('tarot.index')" :class="route().current('tarot.*') ? 'text-accent-text' : ''" :aria-current="route().current('tarot.*') ? 'page' : undefined">Daily card</Link>
                    <Link :href="route('chat.index')">Chat</Link>
                    <Link v-if="page.props.features?.forecast" :href="route('forecast')">Moonoscope</Link>
                    <Link :href="route('credits')">Minutes</Link>
                    <Link :href="route('about')">About</Link>
                </div>
                <div class="flex items-center gap-4"><Link :href="route('login')" class="text-sm font-semibold text-accent-text">Log in</Link><Link :href="route('register')" class="action !px-4 !py-2 text-sm">Get started</Link></div>
            </div>
        </nav>
        <main class="app-content"><slot /></main>
    </div>
</template>
