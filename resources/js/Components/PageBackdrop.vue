<script setup>
import HeroOrbs from '@/Components/HeroOrbs.vue';
</script>
<template>
    <div class="psychic-hero pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="hero-shade absolute inset-0"></div>
        <HeroOrbs class="!z-0" />
    </div>
</template>
