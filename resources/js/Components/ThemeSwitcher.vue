<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const preference = ref(window.psychicTheme.preference);
const setTheme = option => window.psychicTheme.set(option);
const options = ['light', 'dark', 'system'];
const sync = event => { preference.value = event.detail; };
onMounted(() => window.addEventListener('theme-change', sync));
onUnmounted(() => window.removeEventListener('theme-change', sync));
</script>

<template>
    <fieldset class="border-t border-border px-4 py-3 " @click.stop>
        <legend class="sr-only">Website theme</legend>
        <p class="mb-2 text-xs font-semibold text-muted ">Theme</p>
        <div class="flex gap-1">
            <button
                v-for="option in options"
                :key="option"
                type="button"
                :aria-pressed="preference === option"
                :class="preference === option
                    ? 'bg-accent-soft text-accent-text '
                    : 'text-muted hover:bg-surface-hover '"
                class="flex-1 rounded-md px-2 py-2 text-xs font-medium capitalize focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                @click="setTheme(option)"
            >{{ option }}</button>
        </div>
    </fieldset>
</template>
