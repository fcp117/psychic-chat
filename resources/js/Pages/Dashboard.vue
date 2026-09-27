<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
const paths = [
    { number: '01', title: 'A space to connect', text: 'Return to your conversations and make space for what is on your mind.', label: 'Explore your chats', route: 'chat.index' },
    { number: '02', title: 'A little perspective', text: 'Explore published forecasts and find a moment for reflection.', label: 'Visit Forecast', route: 'forecast' },
    { number: '03', title: 'Your reading balance', text: 'See your available credits and stay ready for your next conversation.', label: 'View credits', route: 'credits' },
];
const activePath = ref(0);
const activeItem = computed(() => paths[activePath.value]);
const paused = ref(false);
let carouselTimer;
const nextPath = () => { activePath.value = (activePath.value + 1) % paths.length; };
onMounted(() => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        carouselTimer = window.setInterval(() => { if (!paused.value) nextPath(); }, 5000);
    }
});
onUnmounted(() => window.clearInterval(carouselTimer));
</script>

<template>
    <Head title="Home" />
    <AuthenticatedLayout>
        <section class="psychic-hero dashboard-hero relative isolate flex min-h-[100svh] overflow-hidden">
            <div class="hero-shade absolute inset-0 -z-10"></div>
            <div class="mx-auto flex w-full max-w-7xl flex-col justify-center px-5 pb-7 pt-28 sm:px-10 lg:px-8">
                <div class="flex flex-1 items-center justify-center"><div class="max-w-2xl text-center">
                    <div class="mb-4 inline-flex items-center gap-3 rounded-full border border-primary/20 bg-surface/80 px-4 py-2.5 shadow-sm backdrop-blur"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-soft text-accent-text" aria-hidden="true">✦</span><span class="text-left"><span class="block text-[10px] font-semibold uppercase tracking-[0.16em] text-accent-text">Welcome back</span><span class="block text-sm font-semibold text-content">{{ $page.props.auth.user.name }}</span></span></div>
                    <p class="mb-7 text-xs font-semibold uppercase tracking-[0.3em] text-accent-text">A moment for yourself</p>
                    <h1 class="font-serif hero-title leading-[1.1] tracking-tight text-content">Your journey.<br />A new perspective.</h1>
                    <p class="mx-auto mt-7 max-w-xl text-base leading-7 text-muted">Slow down, explore your questions, and find space for a meaningful conversation.</p>
                    <div class="mt-9 flex flex-wrap justify-center gap-3">
                        <Link :href="route('chat.index')" class="rounded-full bg-primary px-7 py-3.5 text-sm font-semibold text-on-primary hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4">Go to Chat</Link>
                        <Link :href="route('forecast')" class="rounded-full border border-border bg-surface px-7 py-3.5 text-sm font-semibold text-content hover:bg-surface-hover">Explore Forecast</Link>
                    </div>
                </div></div>
                <section class="mt-5 rounded-3xl border border-white/70 bg-surface/80 p-4 shadow-xl shadow-primary/15 backdrop-blur-md sm:p-5" aria-label="Explore Intuition Island" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-soft text-accent-text" aria-hidden="true">✧</span><div><p class="text-xs font-semibold uppercase tracking-widest text-accent-text">Explore Intuition Island</p><h2 class="mt-0.5 font-serif text-2xl text-content sm:text-3xl">Make this moment yours</h2></div></div><div class="flex gap-2 rounded-full bg-page/80 px-3 py-2 shadow-sm" aria-label="Carousel navigation"><button v-for="(item,index) in paths" :key="item.route" type="button" class="h-2.5 rounded-full transition" :class="activePath === index ? 'w-7 bg-primary' : 'w-2.5 bg-border hover:bg-primary/50'" :aria-label="'Show '+item.title" :aria-current="activePath === index ? 'true' : undefined" @click="activePath = index"></button></div></div>
                    <article class="grid gap-4 rounded-2xl border border-border bg-page/75 p-5 shadow-sm sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:p-6" aria-live="polite"><p class="text-xs tracking-widest text-accent-text">{{ activeItem.number }} / INTUITION ISLAND</p><div><h3 class="font-serif text-2xl text-content">{{ activeItem.title }}</h3><p class="mt-2 max-w-2xl text-sm leading-6 text-muted">{{ activeItem.text }}</p></div><Link :href="route(activeItem.route)" class="action shrink-0 text-center !px-5 !py-2.5">{{ activeItem.label }} <span aria-hidden="true">→</span></Link></article>
                </section>
            </div>
        </section>
        <footer class="bg-page px-6 py-6 text-center text-xs text-muted">© 2026 Intuition Island. All rights reserved.</footer>
    </AuthenticatedLayout>
</template>
