<script setup>
import { computed } from 'vue';
const props = defineProps({ password: { type: String, default: '' } });
const requirements = computed(() => [
    { text: 'At least 8 characters', met: Array.from(props.password).length >= 8 },
    { text: 'At least 4 letters', met: (props.password.match(/\p{L}/gu) || []).length >= 4 },
    { text: 'An uppercase and a lowercase letter', met: /\p{Lu}/u.test(props.password) && /\p{Ll}/u.test(props.password) },
    { text: 'At least 1 number', met: /\p{N}/u.test(props.password) },
    { text: 'A special character, such as @, !, or #', met: /[\p{P}\p{S}]/u.test(props.password) },
]);
</script>

<template>
    <ul class="mt-3 space-y-1.5 text-xs leading-5" aria-label="Password requirements">
        <li v-for="item in requirements" :key="item.text" class="flex items-start gap-2" :class="item.met ? 'text-success' : 'text-muted'">
            <span class="inline-block w-3 shrink-0" aria-hidden="true">{{ item.met ? '✓' : '○' }}</span>
            <span><span class="sr-only">{{ item.met ? 'Met: ' : 'Needed: ' }}</span>{{ item.text }}</span>
        </li>
    </ul>
</template>
