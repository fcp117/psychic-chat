<script setup>
import { computed, ref, watch } from 'vue';
const props = defineProps({ user: { type: Object, required: true } });
const failed = ref(false);
watch(() => props.user.profile_photo_url, () => { failed.value = false; });
const initials = computed(() => (props.user.name || '?').trim().split(/\s+/).slice(0, 2).map(n => Array.from(n)[0]).join('').toUpperCase());
</script>
<template>
    <span class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-accent-soft font-semibold text-accent-text">
        <img v-if="user.profile_photo_url && !failed" :src="user.profile_photo_url" :alt="`${user.name}'s profile photo`" class="h-full w-full object-cover" @error="failed = true" />
        <span v-else aria-hidden="true">{{ initials }}</span>
    </span>
</template>