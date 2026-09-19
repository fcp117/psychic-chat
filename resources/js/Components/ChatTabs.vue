<script setup>
import { Link } from '@inertiajs/vue3';
</script>
<template>
    <nav aria-label="Chat options" class="mt-8 flex flex-wrap gap-2 border-b border-border pb-5">
        <Link :href="route('chat.index')" :aria-current="route().current('chat.index') ? 'page' : undefined"
            :class="route().current('chat.index') ? 'bg-primary text-on-primary' : 'bg-surface text-content hover:bg-surface-hover'"
            class="rounded-full border border-border px-6 py-3 text-sm font-semibold">Chat</Link>
        <Link :href="route('psychics.index')" :aria-current="route().current('psychics.index') ? 'page' : undefined"
            :class="route().current('psychics.index') ? 'bg-primary text-on-primary' : 'bg-surface text-content hover:bg-surface-hover'"
            class="rounded-full border border-border px-6 py-3 text-sm font-semibold">Find Psychic</Link>
    </nav>
</template>
