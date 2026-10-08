<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PublicHomeLayout from '@/Layouts/PublicHomeLayout.vue';
import HeroOrbs from '@/Components/HeroOrbs.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth?.user));
const paths = computed(() => [
    { number: '00', title: 'One card. A little perspective.', text: signedIn.value ? 'Take a breath and reflect with 3 free daily draws, saved to your account.' : 'Take a breath and reflect with 1 free daily draw, or join for 3.', label: 'Explore your daily card', route: 'tarot.index', note: 'No credits needed. Reflection, not prediction.' },
    { number: '01', title: 'A space to connect', text: 'Return to your conversations and make space for what is on your mind.', label: 'Explore your chats', route: 'chat.index' },
    { number: '02', title: 'A little perspective', text: 'Explore published forecasts and find a moment for reflection.', label: 'Visit Forecast', route: 'forecast' },
    { number: '03', title: 'Your reading balance', text: 'See your available credits and stay ready for your next conversation.', label: 'View credits', route: 'credits' },
].filter(item => item.route !== 'forecast' || page.props.features?.forecast));
const activePath = ref(0);
const paused = ref(false);
const manuallyPaused = ref(false);
const cardPosition = index => {
    const active = activePath.value % paths.value.length;
    if (index === active) return 'is-current';
    if (paths.value.length === 2) return index > active ? 'is-next' : 'is-previous';
    return index === (active + 1) % paths.value.length ? 'is-next' : 'is-previous';
};
let carouselTimer;
const nextPath = () => { activePath.value = (activePath.value + 1) % paths.value.length; };
onMounted(() => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        carouselTimer = window.setInterval(() => { if (!paused.value && !manuallyPaused.value) nextPath(); }, 5000);
    }
});
onUnmounted(() => window.clearInterval(carouselTimer));
</script>

<template>
    <Head title="Home" />
    <component :is="signedIn ? AuthenticatedLayout : PublicHomeLayout">
        <section class="psychic-hero dashboard-hero relative isolate flex min-h-[100svh] overflow-hidden">
            <div class="hero-shade absolute inset-0 -z-10"></div>
            <HeroOrbs :paused="manuallyPaused" />
            <div class="mx-auto grid w-full max-w-7xl content-center items-center gap-12 px-5 pb-12 pt-32 sm:px-10 lg:grid-cols-[minmax(0,1fr)_23rem] lg:gap-16 lg:px-8 lg:pb-20 lg:pt-36 xl:grid-cols-[minmax(0,1fr)_26rem]">
                <div class="min-w-0"><div class="max-w-2xl text-left">
                    <div class="mb-4 inline-flex items-center gap-3 rounded-full border border-primary/20 bg-surface/80 px-4 py-2.5 shadow-sm backdrop-blur"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-soft text-accent-text" aria-hidden="true">✦</span><span class="text-left"><span class="block text-[10px] font-semibold uppercase tracking-[0.16em] text-accent-text">{{ signedIn ? 'Welcome back' : 'Welcome to' }}</span><span class="block text-sm font-semibold text-content">{{ page.props.auth?.user?.name || 'Intuition Island' }}</span></span></div>
                    <p class="mb-7 text-xs font-semibold uppercase tracking-[0.3em] text-accent-text">A moment for yourself</p>
                    <h1 class="font-serif text-5xl leading-[1.08] tracking-tight text-content sm:text-6xl xl:text-7xl">Your journey.<br />A new perspective.</h1>
                    <p class="mt-7 max-w-lg text-base leading-7 text-muted">Slow down, explore your questions, and find space for a meaningful conversation.</p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <Link :href="route('chat.index')" class="rounded-full bg-primary px-7 py-3.5 text-sm font-semibold text-on-primary hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4">Go to Chat</Link>
                        <Link v-if="page.props.features?.forecast" :href="route('forecast')" class="rounded-full border border-border bg-surface px-7 py-3.5 text-sm font-semibold text-content hover:bg-surface-hover">Explore Forecast</Link>
                    </div>
                </div></div>
                <section class="w-full min-w-0 max-w-md px-4 sm:px-5 lg:justify-self-end" aria-label="Explore Intuition Island" aria-roledescription="carousel" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
                  <div class="card-stack">
                   <div v-for="(item,index) in paths" :key="item.route" class="explore-card rounded-[2rem] border border-border bg-surface/95 p-6 shadow-2xl shadow-primary/15 backdrop-blur-xl sm:p-8" :class="cardPosition(index)" :inert="cardPosition(index) !== 'is-current'" :aria-hidden="cardPosition(index) !== 'is-current'" role="group" aria-roledescription="slide" :aria-label="`${index + 1} of ${paths.length}: ${item.title}`">
                    <div class="flex items-center justify-between gap-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent-soft text-2xl text-accent-text" aria-hidden="true">✧</span>
                        <span class="text-xs tracking-[0.2em] text-muted">{{ String(index + 1).padStart(2, '0') }} / {{ String(paths.length).padStart(2, '0') }}</span>
                    </div>
                    <p class="mt-6 text-[10px] font-semibold uppercase tracking-[0.18em] text-accent-text">Explore Intuition Island</p>
                    <h2 class="mt-3 max-w-xs font-serif text-3xl leading-tight text-content sm:text-4xl">Make this<br />moment yours.</h2>
                    <article class="mt-7 flex min-h-52 flex-col border-t border-border pt-6" aria-live="off">
                        <h3 class="font-serif text-2xl text-content">{{ item.title }}</h3>
                        <p class="mb-6 mt-3 text-sm leading-6 text-muted">{{ item.text }}</p>
                        <Link :href="route(item.route)" class="action mt-auto flex items-center justify-between gap-3 !px-5 !py-3.5">{{ item.label }} <span v-if="item.route !== 'tarot.index'" aria-hidden="true">→</span></Link>
                        <p v-if="item.note" class="mt-3 text-xs leading-5 text-muted">{{ item.note }}</p>
                    </article>
                   </div>
                  </div>
                    <div class="relative z-20 mt-6 flex items-center justify-between gap-3 rounded-full border border-border bg-surface/90 px-3 py-1 backdrop-blur">
                        <div class="flex items-center" aria-label="Carousel navigation"><button v-for="(item,index) in paths" :key="item.route" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" :aria-label="'Show '+item.title" :aria-current="activePath % paths.length === index ? 'true' : undefined" @click="activePath = index"><span class="h-2 rounded-full transition-all motion-reduce:transition-none" :class="activePath % paths.length === index ? 'w-6 bg-primary' : 'w-2 bg-border'"></span></button></div>
                        <button type="button" class="min-h-9 rounded-full px-3 text-xs text-accent-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" :aria-pressed="manuallyPaused" @click="manuallyPaused = !manuallyPaused">{{ manuallyPaused ? 'Resume' : 'Pause' }}</button>
                    </div>
                </section>
            </div>
        </section>
        <footer class="bg-page px-6 py-6 text-center text-xs text-muted">© 2026 Intuition Island. All rights reserved.</footer>
    </component>
</template>

<style scoped>
.card-stack { display: grid; isolation: isolate; perspective: 1000px; }
.explore-card {
    grid-area: 1 / 1;
    min-width: 0;
    transform-origin: center;
    transition: transform 700ms cubic-bezier(.22, 1, .36, 1), filter 700ms ease, opacity 700ms ease;
}
.is-current { z-index: 3; transform: translateX(0) scale(1); filter: blur(0); opacity: 1; }
.is-previous { z-index: 1; transform: translateX(-12%) scale(.91) rotate(-4deg); filter: blur(3px); opacity: .48; pointer-events: none; }
.is-next { z-index: 1; transform: translateX(12%) scale(.91) rotate(4deg); filter: blur(3px); opacity: .48; pointer-events: none; }
@media (prefers-reduced-motion: reduce) {
    .explore-card { transition: none; }
}
</style>
