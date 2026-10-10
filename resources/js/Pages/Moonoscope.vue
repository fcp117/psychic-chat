<script setup>
import {computed,ref} from 'vue';
import {Head,Link,usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PublicHomeLayout from '@/Layouts/PublicHomeLayout.vue';
import MoonSignFinder from '@/Components/MoonSignFinder.vue';
import PageLinks from '@/Components/PageLinks.vue';
import {SIGNS} from '@/lib/moonoscope.mjs';
const props=defineProps({today:String,daily:Object,birthdate:String,forecasts:Object});
const page=usePage(),selected=ref('All');
const lines=computed(()=>props.daily?.lines.filter(l=>selected.value==='All'||l.sign===selected.value)||[]);
function local(utc){return new Date(utc).toLocaleTimeString(undefined,{timeZone:'Asia/Manila',hour:'numeric',minute:'2-digit'});}
</script>
<template>
 <Head title="Moonoscope"/><component :is="page.props.auth?.user?AuthenticatedLayout:PublicHomeLayout">
 <main class="mx-auto max-w-7xl px-4 py-7 sm:px-6">
  <header class="mb-6 max-w-3xl"><p class="text-xs uppercase tracking-[.2em] text-accent-text">By Intuition Island</p><h1 class="mt-2 font-serif text-4xl sm:text-5xl">Moonoscope</h1><p class="mt-3 text-lg text-accent-text">Let the Moon light your way, one day at a time.</p><p class="mt-3 text-sm leading-7 text-muted">Moonoscope honors that ancient connection. Each morning, we offer a gentle message for each of the 12 moon signs, guided by where the Moon is traveling in the heavens that day.</p></header>
  <div class="grid items-start gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
   <MoonSignFinder :birthdate="birthdate" @select="selected=$event"/>
   <section class="min-w-0 rounded-2xl border border-border bg-surface/95 p-5 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs text-muted">{{ today }} · 6 AM Asia/Manila reference</p><h2 class="mt-1 font-serif text-2xl">A moment for your inner world</h2></div><label class="text-sm">Moon sign<select v-model="selected" class="field ml-2"><option>All</option><option v-for="s in SIGNS" :key="s">{{ s }}</option></select></label></div>
    <div v-if="daily" class="mt-4 rounded-xl border border-border bg-page px-4 py-3 text-sm"><p>Moon in {{ daily.facts.sign }} · {{ daily.facts.degree }}° · {{ daily.facts.phase }}</p><p v-for="c in daily.facts.changes" :key="c.utc" class="mt-1 text-xs text-muted">{{ c.from }} → {{ c.to }} at approximately {{ local(c.utc) }} Manila time.</p></div>
    <div v-if="daily" class="mt-4 grid gap-3 sm:grid-cols-2"><article v-for="l in lines" :key="l.sign" class="rounded-xl border border-border bg-page/70 p-4"><h3 class="font-serif text-xl text-accent-text">{{ l.sign }} Moon</h3><p class="mt-2 text-sm leading-7">{{ l.text }}</p></article></div>
    <p v-else class="my-8 text-sm leading-7 text-muted">Today's Moonoscope has not been published yet. You can still discover your Moon sign. Please return after the daily messages have been reviewed.</p>
    <p class="mt-5 text-xs leading-6 text-muted">Moonoscope is shared for reflection, inspiration, and spiritual growth. It is not a substitute for professional medical, financial, or legal advice.</p>
   </section>
  </div>
  <section v-if="page.props.features?.birthChart" class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface p-5"><div><h2 class="font-serif text-xl">Explore your birth chart</h2><p class="mt-1 text-sm text-muted">A free personal chart and downloadable report for registered members.</p></div><Link :href="route('birth-chart')" class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-on-primary">Create your chart</Link></section>
  <details class="mt-5 rounded-2xl border border-border bg-surface p-5"><summary class="cursor-pointer font-semibold">Why the Moon, and how each Moonoscope is born</summary><div class="mt-4 max-w-3xl space-y-3 text-sm leading-7 text-muted"><p>Long before calendars and clocks, people looked to the Moon to understand the rhythms of life. She governs the tides, marks the turning of the months, and for centuries astrologers have seen her as the keeper of our inner world.</p><p>Your sun sign is traditionally seen as the light you shine outward: your purpose, your will, the self the world sees. Your moon sign is the quieter light within. In astrology, it speaks to your emotions, your intuition, your memories, and the secret needs of your heart.</p><p>The Sun lingers about a month in each sign. The Moon moves through a new sign roughly every two and a half days, waxing and waning, ever-changing, much like our feelings. That is why we follow her for daily guidance. She moves as you move.</p><p><strong>Rooted in the true sky.</strong> Every message begins with the Moon's real place among the stars, calculated fresh each morning.</p><p><strong>Spoken to your inner self.</strong> We see which part of your soul's map the Moon is illuminating today, whether home and rest, love and connection, or dreams and creation, and let your message flow from there.</p><p><strong>Only light, always.</strong> Each reading is one or two sentences of loving encouragement. No fear, no warnings. Simply a soft reminder of your own wisdom.</p></div></details>
  <details v-if="forecasts?.data?.length" class="mt-5 rounded-2xl border border-border bg-surface p-5"><summary class="cursor-pointer font-semibold">More reflections · Forecast archive</summary><article v-for="f in forecasts.data" :key="f.id" class="border-b border-border py-4"><h3 class="font-serif text-xl">{{ f.title }}</h3><p class="mt-1 text-xs text-muted">{{ f.published_at.slice(0,10) }}</p><p class="mt-2 whitespace-pre-wrap text-sm leading-7">{{ f.body }}</p></article><PageLinks :data="forecasts"/></details>
 </main></component>
</template>
