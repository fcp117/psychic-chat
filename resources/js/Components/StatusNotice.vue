<script setup>defineProps({ error: Boolean });</script>
<template><div :role="error ? 'alert' : 'status'" class="rounded-xl border border-border p-4 text-sm leading-6" :class="error ? 'bg-surface text-error' : 'bg-accent-soft text-accent-text'"><slot /></div></template>
