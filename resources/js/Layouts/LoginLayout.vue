<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Welcome back' },
    description: { type: String, default: 'Log in to continue your journey with Psychic Chat.' },
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <div class="psychic-hero relative isolate flex min-h-screen min-h-[100svh] flex-col">
        <div class="hero-shade pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
        <header class="w-full">
            <div class="mx-auto flex h-20 max-w-7xl items-center px-5 sm:px-8">
                <Link href="/" class="flex items-center gap-2 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary" aria-label="Psychic Chat home">
                    <ApplicationLogo class="block h-8 w-8 text-primary" />
                    <span class="font-serif text-lg tracking-wide text-content">Psychic Chat</span>
                </Link>
            </div>
        </header>
        <main class="mx-auto flex w-full max-w-7xl flex-1 items-center px-5 py-8 sm:px-8 sm:py-12">
            <section :class="wide ? 'max-w-xl' : 'max-w-md'" class="w-full rounded-3xl border border-border bg-surface p-6 shadow-xl sm:p-10" aria-labelledby="auth-heading">
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.25em] text-accent-text">A moment for yourself</p>
                <h1 id="auth-heading" class="font-serif text-4xl text-content">{{ title }}</h1>
                <p class="mb-8 mt-3 text-sm leading-6 text-muted">{{ description }}</p>
                <slot />
            </section>
        </main>
        <footer class="px-5 py-5 text-center text-xs text-muted">Psychic Chat · Space for reflection and connection.</footer>
    </div>
</template>