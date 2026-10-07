<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
import Modal from '@/Components/Modal.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ reports: Object, blocks: Array, filter: String });
const selected=ref(null), removing=ref(null);
const form=useForm({ action:'resolve', note:'', confirm_ip:false });
const unblock=useForm({});
const review=report=>{selected.value=report;form.reset();form.clearErrors();};
const save=()=>form.patch(route('admin.reports.review',selected.value.id),{preserveScroll:true,onSuccess:()=>selected.value=null});
</script>
<template>
 <Head title="Reports" /><AuthenticatedLayout><AdminWorkspace section="reports">
  <p class="mt-3 text-sm text-muted">Review reports before acting. Account suspension and exact-IP blocking are separate, reversible actions.</p>
  <p v-if="$page.props.flash.success" role="status" class="my-4 rounded-xl bg-accent-soft p-4">{{ $page.props.flash.success }}</p>
  <nav class="my-6 flex gap-2" aria-label="Reported account type"><Link v-for="[key,label] in [['all','All'],['user','Users'],['counselor','Spiritual Coaches']]" :key="key" :href="route('admin.reports',{role:key})" class="rounded-full px-4 py-2 text-sm" :class="filter===key?'bg-primary text-on-primary':'bg-surface border border-border'">{{ label }}</Link></nav>
  <article v-for="report in reports.data" :key="report.id" class="mb-4 rounded-2xl border border-border bg-surface p-5">
   <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ report.reported_name }} · {{ report.role==='counselor'?'Spiritual Coach':'User' }}</h2><span class="text-xs text-muted">{{ report.status }} · {{ report.is_suspended ? 'Suspended' : 'Account active' }}</span></div>
   <p class="mt-2 text-xs text-muted">Reported by {{ report.reporter_name }} · {{ report.created_at }} UTC · Reading #{{ report.chat_session_id }}</p>
   <p class="mt-3 font-semibold capitalize">{{ report.reason }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ report.details }}</p>
   <p class="mt-3 text-xs text-muted">Last observed IP at report time: {{ report.reported_ip || 'Not recorded' }}. This is not proof of identity.</p>
   <p v-if="report.review_note" class="mt-3 whitespace-pre-wrap text-sm text-muted">Review note: {{ report.review_note }}</p>
   <button class="action mt-4 !py-2" @click="review(report)">Review report</button>
  </article><p v-if="!reports.data.length" class="rounded-xl bg-surface p-5">No reports found.</p><PageLinks :data="reports" />
  <h2 class="mb-4 mt-10 font-serif text-2xl">Active IP blocks</h2><p class="mb-4 text-sm text-muted">A shared IP may affect unrelated people. Dynamic IPs and VPNs can bypass these blocks. No ISP-wide blocking is used.</p>
  <div v-for="block in blocks" :key="block.id" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface p-4"><span>{{ block.ip }} — {{ block.reason }}</span><button class="text-accent-text underline" @click="removing=block">Remove block</button></div><p v-if="!blocks.length" class="text-sm text-muted">No IP blocks.</p>
  <Modal :show="!!selected" :closeable="!form.processing" @close="selected=null"><form class="space-y-4 p-6" @submit.prevent="save"><h2 class="font-serif text-2xl">Review {{ selected?.reported_name }}</h2>
   <label class="block">Action<select v-model="form.action" class="field mt-2 w-full"><option value="resolve">Mark reviewed</option><option value="dismiss">Dismiss report</option><option value="suspend">Suspend account and end open readings</option><option value="restore">Restore account access</option><option value="block_ip">Block recorded public IP</option></select></label>
   <label class="block">Review note<textarea v-model="form.note" required minlength="5" maxlength="1000" class="field mt-2 w-full" /></label>
   <label v-if="form.action==='block_ip'" class="flex gap-2 text-sm"><input v-model="form.confirm_ip" type="checkbox" required />I understand this may block others sharing this IP. Private, loopback, and known administrator IPs cannot be blocked.</label>
   <p v-for="error in form.errors" :key="error" role="alert" class="text-error">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" :disabled="form.processing" @click="selected=null">Cancel</button><button class="action" :disabled="form.processing">Confirm action</button></div>
  </form></Modal>
  <Modal :show="!!removing" :closeable="!unblock.processing" @close="removing=null"><div class="space-y-4 p-6"><h2 class="font-serif text-2xl">Remove IP block?</h2><p>{{ removing?.ip }} will be able to access the site again. Suspended accounts remain suspended.</p><p v-for="error in unblock.errors" :key="error" class="text-error">{{ error }}</p><button class="action" :disabled="unblock.processing" @click="unblock.delete(route('admin.ip-blocks.delete',removing.id),{onSuccess:()=>removing=null})">Remove block</button></div></Modal>
 </AdminWorkspace></AuthenticatedLayout>
</template>
