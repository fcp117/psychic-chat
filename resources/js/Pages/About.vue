<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PublicHomeLayout from '@/Layouts/PublicHomeLayout.vue';
import PageBackdrop from '@/Components/PageBackdrop.vue';
import {COMPANY_LINKS} from '@/lib/company-links.mjs';
const page=usePage();
const rainbowOnly=computed(()=>page.props.coachSite?.rainbow_only);
const faqs=computed(()=>[
 ['Who will I speak with?',rainbowOnly.value ? 'Sessions are currently offered with Coach Rainbow. Your reading begins after she accepts your request.' : 'Choose an approved Spiritual Coach, review their profile and rate, and send a reading request. Availability is not guaranteed.'],
 ['Can you predict my future?','No outcome is guaranteed. Intuitive coaching explores patterns, possibilities, and choices. Your decisions remain yours. A session is a blueprint, not a verdict.'],
 ['What can we discuss?','Relationships, career direction, life transitions, personal growth, spiritual development, and next steps. Sessions do not cover health, pregnancy, legal matters, or financial speculation.'],
 ['How should I prepare?','Bring one main question, find a quiet place, and check your internet connection. Open-ended questions allow more room to explore. Share only details you are comfortable sharing.'],
 ['How does payment work?','Purchase minutes in PHP through PayMongo. One minute works equally with every coach. Billing starts after acceptance; each session rounds up to the next whole minute when ended. New purchases expire after 365 days, using soonest-expiring minutes first. Existing converted balances do not expire. Extra time requires your separate upfront checkout approval; there are no automatic charges.'],
 ['Can I book ahead or use voice/video?','Paid sessions take place through live text chat. You can also leave free private messages for a coach or contact technical/credit support while they are offline. Book a Session lets you reserve available office hours using your minutes. Voice and video are not currently available.'],
 ['Do I need an account?','Guests can explore the site and draw one free daily card. Coach profiles, chat, and minute purchases require a verified account. Registered members receive up to three free daily draws.'],
 ['Who is this service for?','Adults aged 18 and over seeking reflection and personal insight. This is not medical, psychological, legal, or financial professional advice, or emergency support.'],
]);
</script>
<template>
 <Head title="About · How it works & FAQ" />
 <component :is="page.props.auth?.user ? AuthenticatedLayout : PublicHomeLayout">
  <div class="relative isolate"><PageBackdrop />
   <main class="mx-auto max-w-4xl space-y-5 px-5 py-7 sm:px-6">
    <header><p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent-text">About Intuition Island</p><h1 class="mt-2 font-serif text-3xl sm:text-4xl">Intuitive insight. Practical clarity.</h1><p class="mt-3 text-sm leading-6 text-muted">{{ rainbowOnly ? 'Private, live intuitive coaching with Rainbow.' : 'Private conversations with Spiritual Coaches to explore your questions and the choices ahead.' }} Your journey and decisions remain yours.</p></header>
    <section class="rounded-2xl border border-border bg-surface/95 p-5"><h2 class="font-serif text-2xl">Meet Coach Rainbow</h2><p class="mt-2 text-sm leading-6 text-muted">Rainbow is a lawyer, author, educator, Reiki master, spiritual life coach, and intuitive reader. She has worked with people online and in person, bringing precision, depth, and genuine heart to her readings.</p><p class="mt-2 text-sm leading-6 text-muted">Her intuitive name reflects her belief that life comes in many colors. Through intuitive tools and practical coaching, she offers a thoughtful conversation—not a fixed prediction—to help you explore patterns and choose your next steps.</p></section>
    <section class="rounded-2xl border border-border bg-surface/95 p-5"><h2 class="font-serif text-2xl">How it works</h2><ol class="mt-3 grid gap-3 text-sm sm:grid-cols-3"><li><strong>1. Find your coach</strong><p class="mt-1 leading-6 text-muted">{{ rainbowOnly ? 'Connect with Rainbow through the coach directory.' : 'Explore coach profiles and choose someone you connect with.' }}</p></li><li><strong>2. Review & request</strong><p class="mt-1 leading-6 text-muted">Review the terms, add minutes, and request a chat. No billing while waiting for acceptance.</p></li><li><strong>3. Explore together</strong><p class="mt-1 leading-6 text-muted">Bring your question and reflect together. Leave with next steps you can choose for yourself.</p></li></ol><Link :href="route(page.props.auth?.user ? 'psychics.index' : 'register')" class="action mt-4 inline-block !py-2 text-sm">{{ page.props.auth?.user ? (rainbowOnly ? 'Connect with Rainbow' : 'Find a Spiritual Coach') : 'Create your account' }}</Link></section>
    <section class="rounded-2xl border border-border bg-surface/95 p-5"><h2 class="mb-2 font-serif text-2xl">Frequently asked questions</h2><details v-for="[question,answer] in faqs" :key="question" class="border-b border-border py-3 last:border-0"><summary class="cursor-pointer text-sm font-semibold">{{ question }}</summary><p class="mt-2 text-sm leading-6 text-muted">{{ answer }}</p></details></section>
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface p-4"><div><h2 class="font-serif text-xl">More from Intuition Island</h2><p class="mt-1 text-sm text-muted">Explore our Etsy shop. Shop purchases are separate from your website minutes.</p></div><a :href="COMPANY_LINKS[0].url" target="_blank" rel="noopener noreferrer" class="action !py-2.5">Visit our Etsy shop<span class="sr-only"> (opens in a new tab)</span></a></section>
    <p class="text-xs leading-5 text-muted">For reflection and personal insight, not guaranteed outcomes or professional advice. Read our <Link :href="route('service.disclaimer')" class="text-accent-text underline">service disclaimer</Link>, <Link :href="route('terms')" class="text-accent-text underline">terms</Link>, and <Link :href="route('privacy')" class="text-accent-text underline">privacy policy</Link>.</p>
   </main>
  </div>
 </component>
</template>
