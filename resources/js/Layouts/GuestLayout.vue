<script setup>
import LoginLayout from '@/Layouts/LoginLayout.vue';
defineProps({
    title: { type: String, default: 'Welcome to Psychic Chat' },
    description: { type: String, default: 'A space for reflection and connection.' },
});
</script>
<template><LoginLayout :title="title" :description="description"><slot /></LoginLayout></template>
