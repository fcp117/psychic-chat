<script setup>
import { computed, nextTick, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PublicHomeLayout from '@/Layouts/PublicHomeLayout.vue';
import PageBackdrop from '@/Components/PageBackdrop.vue';

const props = defineProps({ tarot: Object });
const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth?.user));
const activeId = ref(props.tarot.draws.at(-1)?.id);
const phase = ref(activeId.value ? 'revealed' : 'ready');
const selected = computed(() => props.tarot.draws.find(draw => draw.id === activeId.value));
const reading = computed(() => selected.value?.reading);
const resultHeading = ref();
const form = useForm({ request_token: crypto.randomUUID() });
const dateLabel = computed(() => new Date(`${props.tarot.date}T12:00:00+08:00`).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' }));
let shuffleTimer;
function begin() {
    if (Date.now() >= Date.parse(props.tarot.resets_at)) {
        router.reload({ only: ['tarot'], onSuccess: () => { activeId.value = props.tarot.draws.at(-1)?.id; phase.value = activeId.value ? 'revealed' : 'ready'; } });
        return;
    }
    form.clearErrors();
    phase.value = 'shuffling';
    shuffleTimer = window.setTimeout(() => { phase.value = 'choosing'; }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 750);
}
function draw() {
    if (form.processing || phase.value !== 'choosing') return;
    form.post(route('tarot.draw'), {
        preserveScroll: true,
        onSuccess: async () => {
            activeId.value = props.tarot.draws.at(-1)?.id;
            phase.value = 'revealed';
            form.request_token = crypto.randomUUID();
            await nextTick();
            resultHeading.value?.focus({ preventScroll: true });
        },
    });
}
function reopen(draw) {
    if (form.processing) return;
    clearTimeout(shuffleTimer);
    form.clearErrors();
    activeId.value = draw.id;
    phase.value = 'revealed';
}
onUnmounted(() => clearTimeout(shuffleTimer));
</script>

<template>
    <Head title="Your daily tarot reflection" />
    <component :is="signedIn ? AuthenticatedLayout : PublicHomeLayout">
        <div class="relative isolate min-h-[80vh]">
            <PageBackdrop v-if="!signedIn" />
            <main class="mx-auto max-w-6xl px-4 py-8 sm:px-8 sm:py-12">
                <header class="mb-8 flex flex-wrap items-end justify-between gap-5">
                    <div><p class="text-[10px] font-semibold uppercase tracking-[.25em] text-accent-text">A little ritual, just for you</p><h1 class="mt-3 font-serif text-4xl sm:text-5xl">Your daily card</h1><p class="mt-3 text-sm text-muted">{{ dateLabel }} · A moment to pause. A new way to reflect.</p></div>
                    <div class="rounded-2xl border border-border bg-surface px-5 py-3"><p class="text-sm font-semibold text-content">{{ tarot.remaining }} / {{ tarot.limit }} free draws remaining</p><p class="mt-1 text-xs text-muted">Resets at midnight · Philippine time</p></div>
                </header>

                <section class="ritual-panel relative overflow-hidden rounded-[2rem] border border-border bg-surface p-6 shadow-xl sm:p-10" :aria-busy="form.processing">
                    <div class="ritual-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>
                    <div v-if="phase === 'revealed' && reading" class="relative grid items-center gap-9 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                        <figure :key="selected.id" class="card-reveal mx-auto w-full max-w-[270px]">
                            <img :src="reading.image_url" :alt="`${reading.name} tarot artwork`" class="aspect-[2/3] w-full rounded-2xl border border-amber-200/30 object-cover shadow-2xl" width="1024" height="1536" />
                            <figcaption class="mt-4 text-center text-[10px] uppercase tracking-[.2em] text-muted">{{ reading.category }} · Upright</figcaption>
                        </figure>
                        <article class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[.23em] text-accent-text">Your card, your moment</p>
                            <h2 ref="resultHeading" tabindex="-1" class="mt-3 font-serif text-4xl outline-none sm:text-5xl">{{ reading.name }}</h2>
                            <p class="mt-4 text-sm font-medium text-accent-text">{{ reading.keywords }}</p>
                            <p class="mt-5 whitespace-pre-line break-words leading-7 text-muted">{{ reading.meaning }}</p>
                            <div class="mt-6 border-t border-border pt-5"><h3 class="text-xs font-semibold uppercase tracking-widest text-content">A small step today</h3><p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-muted">{{ reading.guidance }}</p></div>
                            <div class="mt-5 rounded-2xl border border-primary/20 bg-accent-soft p-5"><h3 class="text-[10px] font-semibold uppercase tracking-widest text-accent-text">Something to sit with</h3><p class="mt-2 whitespace-pre-line break-words font-serif text-xl leading-relaxed">{{ reading.reflection }}</p></div>
                            <button v-if="tarot.remaining && tarot.available" type="button" class="action mt-6" @click="begin">Draw another card <span aria-hidden="true">↗</span></button>
                            <p v-else class="mt-5 text-sm text-muted">Your readings are saved. Come back tomorrow for a fresh reflection.</p>
                        </article>
                    </div>
                    <div v-else class="relative grid items-center gap-5 md:grid-cols-2 md:gap-12">
                        <div class="py-4">
                            <span class="mb-6 inline-flex rounded-full border border-border bg-page/60 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-widest text-accent-text">Free daily reflection · No credits needed</span>
                            <h2 class="max-w-md font-serif text-4xl leading-tight sm:text-5xl">A quiet moment.<br />An open mind.</h2>
                            <p class="mt-5 max-w-md leading-7 text-muted">Take a breath and consider: “What could I reflect on today?” You don’t need to type or share your question.</p>
                            <p v-if="!tarot.available" class="mt-6 text-accent-text">Our deck is being prepared. Please check back soon.</p>
                            <template v-else-if="tarot.remaining">
                                <button v-if="phase === 'ready'" type="button" class="action mt-7" @click="begin">Shuffle the cards <span aria-hidden="true">✧</span></button>
                                <p v-else role="status" class="mt-7 text-sm font-semibold text-accent-text">{{ form.processing ? 'Saving your card…' : phase === 'shuffling' ? 'Shuffling your moment of reflection…' : 'Choose a face-down card to reveal your reflection.' }}</p>
                            </template>
                            <div v-else class="mt-6"><p class="font-semibold">You’ve used today’s free draws.</p><p class="mt-2 text-sm text-muted">Return after midnight Philippine time.</p><button type="button" class="mt-3 text-sm text-accent-text underline" @click="router.reload({ only: ['tarot'] })">Check today’s allowance</button></div>
                            <p class="mt-5 text-xs leading-5 text-muted">{{ tarot.deck_size }}-card curated deck · Upright readings<br />Cards are selected randomly when you choose. Each draw is a separate reflection.</p>
                        </div>
                        <div class="card-fan mx-auto w-full max-w-sm" :class="{ 'is-shuffling': phase === 'shuffling', 'is-choosing': phase === 'choosing' }">
                            <button v-for="n in 3" :key="n" type="button" :style="{ '--slot': n - 2 }" :disabled="phase !== 'choosing' || form.processing" :aria-label="`Choose face-down card ${n}; uses one free draw`" class="card-back focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary" @click="draw">
                                <span class="card-back-frame"><span class="text-[8px] uppercase tracking-[.22em]">Intuition Island</span><span class="celestial-seal" aria-hidden="true">✧</span><span class="text-[8px] uppercase tracking-[.24em]">Pause · Reflect</span></span>
                            </button>
                        </div>
                    </div>
                    <div v-if="Object.keys(form.errors).length" role="alert" class="relative mt-6 rounded-xl border border-red-400/30 bg-red-400/10 p-4 text-sm text-red-400"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p><button type="button" class="mt-2 underline" @click="router.reload({ only: ['tarot'] })">Refresh saved readings and allowance</button></div>
                </section>

                <section v-if="tarot.draws.length" class="mt-8" aria-label="Today's saved readings">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2"><h2 class="font-serif text-2xl">Today’s reflections</h2><span class="text-xs text-muted">Reopen freely. No extra draws used.</span></div>
                    <div class="grid gap-3 sm:grid-cols-3"><button v-for="(item, index) in tarot.draws" :key="item.id" type="button" :disabled="form.processing" :aria-pressed="activeId === item.id && phase === 'revealed'" class="flex items-center gap-4 rounded-2xl border bg-surface p-3 text-left transition hover:bg-surface-hover" :class="activeId === item.id && phase === 'revealed' ? 'border-primary' : 'border-border'" @click="reopen(item)"><img :src="item.reading.image_url" alt="" class="h-16 w-11 rounded-md object-cover" /><span><span class="block text-[10px] uppercase tracking-widest text-muted">Reflection {{ index + 1 }}</span><span class="mt-1 block font-serif text-lg">{{ item.reading.name }}</span></span><span class="ml-auto text-accent-text" aria-hidden="true">↗</span></button></div>
                </section>
                <div v-if="!signedIn" class="mt-7 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-surface p-5"><div><h2 class="font-serif text-xl">Make reflection a daily ritual.</h2><p class="mt-1 text-sm text-muted">Create an account for 3 free draws a day. Today’s guest draw counts toward your 3.</p></div><Link :href="route('register')" class="action">Create a free account</Link></div>
                <footer class="mx-auto mt-7 max-w-3xl text-center text-xs leading-6 text-muted"><p>Automated, prewritten reflections—not a live Spiritual Coach reading, a guaranteed prediction, or professional advice. Take what feels useful and leave the rest.</p><p v-if="!signedIn" class="mt-2">A browser cookie remembers your guest allowance. Clearing cookies or using another browser can reset guest identification. After you sign in, claimed readings are available only in that account.</p><Link :href="route('privacy')" class="underline">Privacy notice</Link></footer>
            </main>
        </div>
    </component>
</template>

<style scoped>
.ritual-glow { background: radial-gradient(ellipse at 22% 45%, rgb(167 139 250 / .13), transparent 60%); }
.card-fan { position: relative; height: 355px; perspective: 1000px; }
.card-back { position: absolute; width: 168px; height: 252px; top: 50px; left: calc(50% - 84px); border-radius: 15px; border: 1px solid #a89470; color: #d7c398; background: radial-gradient(ellipse at 50% 30%, #493165, #191022 80%); box-shadow: 0 18px 40px #0005; transform: translateX(calc(var(--slot) * 62px)) translateY(calc(var(--slot) * var(--slot) * 13px)) rotate(calc(var(--slot) * 12deg)); transition: transform .5s, box-shadow .3s; }
.card-back-frame { position: absolute; inset: 10px; border: 1px solid #a8947060; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 20px 4px; }
.celestial-seal { display: grid; place-items: center; width: 85px; height: 85px; border: 1px solid #a8947080; border-radius: 50%; outline: 1px solid #a8947030; outline-offset: 8px; font-size: 60px; }
.is-choosing .card-back:not(:disabled):hover, .is-choosing .card-back:focus-visible { z-index: 5; transform: translateX(calc(var(--slot) * 62px)) translateY(-15px) rotate(0); box-shadow: 0 8px 40px #b399f540; }
.is-shuffling .card-back { animation: shuffle .7s ease-in-out both; }
.card-reveal { animation: reveal .8s cubic-bezier(.2,.8,.2,1) both; }
@keyframes shuffle { 0%,100% { transform: translateX(calc(var(--slot) * 62px)) rotate(calc(var(--slot) * 12deg)); } 40% { transform: translateX(calc(var(--slot) * -60px)) translateY(-18px) rotate(calc(var(--slot) * -9deg)); } 70% { transform: translateX(0) rotate(0); } }
@keyframes reveal { from { opacity: 0; transform: perspective(900px) rotateY(-85deg) scale(.94); } to { opacity: 1; transform: perspective(900px) rotateY(0) scale(1); } }
@media (max-width: 380px) { .card-back { width: 142px; height: 222px; left: calc(50% - 71px); } .card-fan { transform: scale(.9); height: 310px; } }
@media (prefers-reduced-motion: reduce) { .card-back, .card-reveal { animation: none !important; transition: none !important; } }
</style>
