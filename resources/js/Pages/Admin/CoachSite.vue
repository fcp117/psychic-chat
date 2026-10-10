<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
const props=defineProps({ settings:Object, coaches:Array });
const form=useForm({...props.settings});
</script>
<template>
 <Head title="Coach availability" />
 <AuthenticatedLayout><AdminWorkspace section="coach-site">
  <form class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface p-5" @submit.prevent="form.patch(route('admin.coach-site.update'),{preserveScroll:true})">
   <p v-if="$page.props.flash.success" role="status" class="text-sm text-accent-text">{{ $page.props.flash.success }}</p>
   <label class="block text-sm font-semibold">Rainbow’s account<select v-model="form.rainbow_user_id" class="field mt-2 w-full"><option :value="null">Select an account</option><option v-for="coach in coaches" :key="coach.id" :value="coach.id">{{ coach.name }} · {{ coach.email }}</option></select></label>
   <p class="text-xs text-muted">Only approved, verified, active coaches can be selected. This does not rename their account.</p>
   <label class="flex items-start gap-3"><input v-model="form.rainbow_only" type="checkbox" class="mt-1" /><span class="text-sm"><strong>Rainbow-only mode</strong><span class="mt-1 block text-muted">Only the selected coach appears in the directory and can receive or accept new readings. About content focuses on Rainbow.</span></span></label>
   <label class="flex items-start gap-3"><input v-model="form.applications_open" type="checkbox" class="mt-1" /><span class="text-sm"><strong>Accept coach applications</strong><span class="mt-1 block text-muted">Show the application link and accept submissions. This works independently of Rainbow-only mode.</span></span></label>
   <p class="text-xs leading-5 text-muted">Other accounts and chat history are preserved. Active readings continue. Previously submitted applications remain available for review.</p>
   <p v-for="error in form.errors" :key="error" role="alert" class="text-sm text-error">{{ error }}</p>
   <button class="action !py-2" :disabled="form.processing">Save settings</button>
  </form>
 </AdminWorkspace></AuthenticatedLayout>
</template>
