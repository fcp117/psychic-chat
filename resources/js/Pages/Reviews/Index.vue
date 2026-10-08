<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLinks from '@/Components/PageLinks.vue';
import Modal from '@/Components/Modal.vue';
defineProps({ sessions:Object, windowHours:Number });
const page=usePage(), selected=ref(null);
const form=useForm({rating:0,comment:'',publish_consent:true});
const open=s=>{selected.value=s;form.reset();form.clearErrors();};
const submit=()=>form.post(route('reviews.store',selected.value.id),{preserveScroll:true,onSuccess:()=>selected.value=null});
const date=value=>value?new Date(value).toLocaleString():'';
</script>
<template><Head title="Reading feedback" /><AuthenticatedLayout><main class="mx-auto max-w-3xl px-5 py-10">
<p class="text-xs uppercase tracking-widest text-accent-text">Your experience matters</p><h1 class="mt-3 font-serif text-4xl">Reading feedback</h1><p class="mt-3 text-sm leading-6 text-muted">Rate a completed reading within {{ windowHours }} hours. Stars contribute immediately to the coach’s average. Comments go directly to the admin team for review before any highlighting.</p>
<p v-if="page.props.flash?.success" role="status" class="mt-5 rounded-xl bg-accent-soft p-4 text-sm">{{ page.props.flash.success }}</p>
<div class="mt-6 space-y-3"><article v-for="session in sessions.data" :key="session.id" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-surface p-5"><div><h2 class="font-serif text-xl">{{ session.coach }}</h2><p class="mt-1 text-xs text-muted">Reading #{{ session.id }} · Ended {{ date(session.ended_at) }}</p><p v-if="session.eligible" class="mt-1 text-xs text-muted">Feedback closes {{ date(session.deadline) }}</p></div><button v-if="session.eligible" class="action" @click="open(session)">Leave feedback</button><span v-else class="text-xs text-muted">{{ session.submitted?'Feedback submitted':'Feedback window closed' }}</span></article><p v-if="!sessions.data.length" class="rounded-2xl border border-border bg-surface p-8 text-center text-muted">Your completed readings will appear here.</p></div><PageLinks :data="sessions" />
<Modal max-width="md" :show="!!selected" :closeable="!form.processing" @close="selected=null"><form class="space-y-3 p-5" @submit.prevent="submit"><h2 class="font-serif text-xl">Your reading with {{ selected?.coach }}</h2><fieldset><legend class="text-sm font-semibold">Your rating</legend><div class="mt-1 flex gap-1"><label v-for="n in 5" :key="n" class="cursor-pointer"><input v-model="form.rating" type="radio" :value="n" name="rating" required class="peer sr-only" :aria-label="`${n} out of 5 stars`" /><span class="block rounded-lg p-2 text-2xl peer-focus-visible:outline" :class="form.rating>=n?'text-accent-text':'text-muted'">{{ form.rating>=n?'★':'☆' }}</span></label></div></fieldset><label class="block text-sm">Your comments<textarea v-model="form.comment" required minlength="5" maxlength="2000" rows="3" class="field mt-1 w-full text-sm" placeholder="What was helpful? What could be better?" /></label><p class="text-xs text-muted">Avoid personal or sensitive details. Stars update the coach’s average.</p><p class="text-xs leading-5 text-muted">By submitting, you allow admin review and anonymous highlighting as “Verified client.”</p><p v-for="error in form.errors" :key="error" role="alert" class="text-sm text-error">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" :disabled="form.processing" @click="selected=null">Cancel</button><button class="action" :disabled="form.processing || !form.rating">{{ form.processing?'Sending…':'Send feedback' }}</button></div></form></Modal>
</main></AuthenticatedLayout></template>
