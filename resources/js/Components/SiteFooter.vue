<script setup>
import {COMPANY_LINKS} from '@/lib/company-links.mjs';
import ReflectionMusic from './ReflectionMusic.vue';
</script>
<template>
 <footer class="relative z-10 border-t border-border bg-page px-5 py-4 text-xs text-muted">
  <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-5 gap-y-2">
   <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
    <p>© {{ new Date().getFullYear() }} Intuition Island. All rights reserved.</p>
    <ReflectionMusic />
   </div>
   <nav aria-label="Intuition Island social links" class="flex flex-wrap items-center gap-x-4 gap-y-1">
    <a v-for="link in COMPANY_LINKS" :key="link.name" :href="link.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center text-accent-text underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">{{ link.name }}<span class="sr-only"> (opens in a new tab)</span></a>
   </nav>
  </div>
 </footer>
</template>
