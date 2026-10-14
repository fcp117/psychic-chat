<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
import Modal from '@/Components/Modal.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ reports: Object, blocks: Array, filter: String });
const role=usePage().props.auth.user.role;
const actionLabels={resolve:'Resolve report',dismiss:'Dismiss report',suspend:'Suspend account',ban:'Ban account',restore:'Restore account access',block_ip:'Block recorded public IP'};
const statusLabels={open:'Awaiting Support',returned:'Returned to Support',manager_review:'Awaiting Manager',admin_review:'Awaiting Admin',reviewed:'Completed',dismissed:'Dismissed'};
const proposed=useForm({action:'resolve',note:'',confirm_ip:false}),approval=useForm({approve:true,note:''});
const workflow=ref(null);
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
   <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ report.reported_name }} · {{ report.role==='counselor'?'Spiritual Coach':'User' }}</h2><span class="text-xs text-muted">{{ statusLabels[report.status] || report.status }} · {{ report.is_suspended ? 'Suspended' : 'Account active' }}</span></div>
   <p class="mt-2 text-xs text-muted">Reported by {{ report.reporter_name }} · {{ report.created_at }} UTC · Reading #{{ report.chat_session_id }}</p>
   <p class="mt-3 font-semibold capitalize">{{ report.reason }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ report.details }}</p>
   <p class="mt-3 text-xs text-muted">Last observed IP at report time: {{ report.reported_ip || 'Not recorded' }}. This is not proof of identity.</p>
   <p v-if="report.review_note" class="mt-3 whitespace-pre-wrap text-sm text-muted">Review note: {{ report.review_note }}</p>
   <p v-if="report.support_note" class="mt-2 text-sm">Support recommendation: {{report.proposed_action}} — {{report.support_note}}</p><p v-if="report.manager_note" class="mt-2 text-sm">Manager: {{report.manager_note}}</p>
   <button v-if="['support','admin'].includes(role)&&['open','returned','reviewed','dismissed'].includes(report.status)" class="action mt-3 !py-2" @click="workflow=report;proposed.reset()">Recommend to Manager</button>
   <button v-if="role==='manager'&&report.status==='manager_review'" class="action mt-3 !py-2" @click="workflow=report;approval.reset()">Review recommendation</button>
   <button v-if="role==='admin'&&report.status==='admin_review'" class="action mt-3 !py-2" @click="review(report);form.action=report.proposed_action">Final decision</button>
  </article><p v-if="!reports.data.length" class="rounded-xl bg-surface p-5">No reports found.</p><PageLinks :data="reports" />
  <section v-if="role==='admin'"><h2 class="mb-4 mt-10 font-serif text-2xl">Active IP blocks</h2><p class="mb-4 text-sm text-muted">A shared IP may affect unrelated people. Dynamic IPs and VPNs can bypass these blocks. No ISP-wide blocking is used.</p>
  <div v-for="block in blocks" :key="block.id" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface p-4"><span>{{ block.ip }} — {{ block.reason }}</span><button class="text-accent-text underline" @click="removing=block">Remove block</button></div><p v-if="!blocks.length" class="text-sm text-muted">No IP blocks.</p></section>
  <Modal :show="!!selected" :closeable="!form.processing" @close="selected=null"><form class="space-y-4 p-6" @submit.prevent="save"><h2 class="font-serif text-2xl">Review {{ selected?.reported_name }}</h2>
   <label class="block">Final decision<select v-model="form.action" class="field mt-2 w-full"><option :value="selected?.proposed_action">Approve: {{actionLabels[selected?.proposed_action]}}</option><option v-if="selected?.proposed_action!=='dismiss'" value="dismiss">Reject recommendation and dismiss report</option></select></label>
   <label class="block">Review note<textarea v-model="form.note" required minlength="5" maxlength="1000" class="field mt-2 w-full" /></label>
   <label v-if="form.action==='block_ip'" class="flex gap-2 text-sm"><input v-model="form.confirm_ip" type="checkbox" required />I understand this may block others sharing this IP. Private, loopback, and known administrator IPs cannot be blocked.</label>
   <p v-for="error in form.errors" :key="error" role="alert" class="text-error">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" :disabled="form.processing" @click="selected=null">Cancel</button><button class="action" :disabled="form.processing">Confirm action</button></div>
  </form></Modal>
  <Modal :show="!!removing" :closeable="!unblock.processing" @close="removing=null"><div class="space-y-4 p-6"><h2 class="font-serif text-2xl">Remove IP block?</h2><p>{{ removing?.ip }} will be able to access the site again. Suspended accounts remain suspended.</p><p v-for="error in unblock.errors" :key="error" class="text-error">{{ error }}</p><button class="action" :disabled="unblock.processing" @click="unblock.delete(route('admin.ip-blocks.delete',removing.id),{onSuccess:()=>removing=null})">Remove block</button></div></Modal>
 <Modal :show="!!workflow" max-width="md" @close="workflow=null"><form class="space-y-3 p-5" @submit.prevent="role==='manager'?approval.post(route('staff.reports.manager',workflow.id),{onSuccess:()=>workflow=null}):proposed.post(route('staff.reports.recommend',workflow.id),{onSuccess:()=>workflow=null})">
 <h2 class="font-serif text-xl">{{role==='manager'?'Manager review':'Support recommendation'}}</h2><p class="text-xs text-muted">No restriction takes effect before final Admin approval.</p>
 <template v-if="role==='manager'"><select v-model="approval.approve" class="field w-full" aria-label="Manager decision"><option :value="true">Approve and send to Admin</option><option :value="false">Return to Support</option></select><textarea v-model="approval.note" required minlength="10" maxlength="1000" class="field w-full" aria-label="Manager note" /></template>
 <template v-else><select v-model="proposed.action" class="field w-full" aria-label="Recommended action"><option value="resolve">Resolve</option><option value="dismiss">Dismiss</option><option value="suspend">Suspend account</option><option value="ban">Ban account</option><option value="restore">Restore account</option><option value="block_ip">Block recorded IP</option></select><textarea v-model="proposed.note" required minlength="10" maxlength="1000" class="field w-full" aria-label="Support note" /><label v-if="proposed.action==='block_ip'" class="flex gap-2 text-xs"><input v-model="proposed.confirm_ip" type="checkbox" required />I understand a shared IP can affect other people.</label></template>
 <p v-for="e in {...proposed.errors,...approval.errors}" :key="e" class="text-error">{{e}}</p><button class="action" :disabled="proposed.processing||approval.processing">Submit review</button></form></Modal>
 </AdminWorkspace></AuthenticatedLayout>
</template>
