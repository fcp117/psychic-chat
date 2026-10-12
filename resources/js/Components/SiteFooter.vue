<script setup>
import ReflectionMusic from './ReflectionMusic.vue';
import { Link } from '@inertiajs/vue3';
</script>
<template>
 <footer class="relative z-10 border-t border-border bg-page px-5 py-4 text-xs text-muted">
  <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-5 gap-y-2">
   <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
    <p>© {{ new Date().getFullYear() }} Intuition Island. All rights reserved.</p>
    <ReflectionMusic />
   </div>
   <nav aria-label="Legal information" class="flex flex-wrap items-center gap-x-4">
    <Link :href="route('terms')" class="inline-flex min-h-10 items-center hover:underline">Terms of Service</Link>
    <Link :href="route('privacy')" class="inline-flex min-h-10 items-center hover:underline">Privacy Notice</Link>
   </nav>
  </div>
 </footer>
</template>
