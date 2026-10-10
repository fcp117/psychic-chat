<script setup>
import {computed,ref} from 'vue';
import {Head,useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
const props=defineProps({templates:Array});const point=ref('Sun'),selected=ref(null);
const list=computed(()=>props.templates.filter(t=>t.point===point.value));
const form=useForm({body:'',version:1,reviewed:false});
function edit(t){selected.value=t.id;form.body=t.body||'';form.version=t.version;form.reviewed=false;form.clearErrors();}
function save(){form.put(route('admin.birth-chart.update',selected.value),{preserveScroll:true,onSuccess:()=>selected.value=null});}
</script>
<template><Head title="Birth chart interpretations"/><AuthenticatedLayout><AdminWorkspace section="birth-chart">
 <p class="rounded-xl bg-surface p-3 text-sm leading-7 text-muted">The client supplied one Cancer Sun / Aries Moon example, not a complete interpretation library. Only those two excerpts are seeded. Add client-approved wording for other placements; blank entries stay unpublished. These templates do not change calculations.</p>
 <p v-if="$page.props.flash?.success" role="status" class="my-3 text-accent-text">{{ $page.props.flash.success }}</p>
 <label class="my-4 block text-sm">Placement<select v-model="point" class="field ml-3"><option>Sun</option><option>Moon</option><option>Ascendant</option></select></label>
 <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><button v-for="t in list" :key="t.id" class="rounded-xl border border-border bg-page p-4 text-left" @click="edit(t)"><span class="font-serif text-xl">{{ t.sign }}</span><span class="mt-2 block text-xs text-muted">{{ t.body?'Approved text available':'Needs client-approved text' }}</span></button></div>
 <form v-if="selected" class="mt-5 rounded-xl border border-border bg-surface p-4" @submit.prevent="save"><h3 class="font-serif text-xl">{{ templates.find(t=>t.id===selected)?.point }} in {{ templates.find(t=>t.id===selected)?.sign }}</h3><label class="mt-3 block text-sm">Interpretation<textarea v-model="form.body" rows="5" maxlength="1200" class="field mt-1 w-full"/></label><p class="mt-2 text-xs text-muted">Up to 1,200 characters. No guaranteed predictions or professional advice. Clearing the text removes it from new reports.</p><label class="my-3 flex gap-2 text-sm"><input v-model="form.reviewed" type="checkbox" required/>I reviewed and approved this wording for reports.</label><p v-for="e in form.errors" :key="e" role="alert" class="text-error">{{ e }}</p><button class="action" :disabled="form.processing">Save approved text</button><button type="button" class="ml-4 text-sm underline" @click="selected=null">Cancel</button></form>
 </AdminWorkspace></AuthenticatedLayout></template>
