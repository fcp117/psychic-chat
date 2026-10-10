<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLinks from '@/Components/PageLinks.vue';
const props=defineProps({threads:Object,thread:Object,messages:Object,category:String});
const page=usePage();const admin=computed(()=>page.props.auth.user.role==='admin');
const form=useForm({thread:props.thread?.id||null,category:props.thread?.category||'technical',body:'',request_key:crypto.randomUUID()});
watch(()=>props.thread,t=>{form.thread=t?.id||null;form.category=t?.category||'technical';form.body='';form.request_key=crypto.randomUUID();});
let timer;onMounted(()=>{timer=setInterval(()=>{if(!form.processing)router.reload({only:['threads','messages']});},15000);});onUnmounted(()=>clearInterval(timer));
const send=()=>form.post(route('support.send'),{preserveScroll:true,onSuccess:()=>{form.body='';form.request_key=crypto.randomUUID();}});
</script>
<template>
 <Head title="Private support messages" /><AuthenticatedLayout><main class="mx-auto max-w-5xl space-y-4 px-4 py-6">
  <header><h1 class="font-serif text-3xl">Private support messages</h1><p class="mt-1 text-sm text-muted">Technical and credit questions stay separate. Messages are free; support will reply when available.</p></header>
  <nav class="flex flex-wrap gap-3 text-sm"><Link :href="route('support.index')">All</Link><Link :href="route('support.index',{category:'technical'})">Technical issues</Link><Link :href="route('support.index',{category:'credits'})">Minutes & payment questions</Link><Link :href="route('psychics.index')" class="text-accent-text underline">Message a coach instead</Link></nav>
  <div class="grid gap-4 md:grid-cols-[16rem_1fr]"><aside class="rounded-2xl border border-border bg-surface p-3"><Link v-for="item in threads.data" :key="item.id" :href="route('support.index',{thread:item.id})" class="mb-2 block rounded-xl p-3 text-sm" :class="thread?.id===item.id?'bg-accent-soft':'bg-page'"><strong>{{ item.category==='technical'?'Technical issues':'Credit questions' }}</strong><p v-if="admin" class="text-xs text-muted">{{ item.name }}</p><span v-if="item.unread" class="text-xs text-accent-text">{{ item.unread }} unread</span></Link><p v-if="!threads.data.length" class="text-sm text-muted">No conversations yet.</p><PageLinks :data="threads" /></aside>
   <section class="space-y-3 rounded-2xl border border-border bg-surface p-4"><h2 class="font-serif text-xl">{{ thread?(thread.category==='technical'?'Technical support':'Minutes & payment support'):'Start a support conversation' }}</h2>
    <div v-if="messages" class="max-h-96 space-y-3 overflow-y-auto"><article v-for="m in [...messages.data].reverse()" :key="m.id" class="rounded-xl bg-page p-3 text-sm"><div class="flex justify-between gap-3 text-xs text-muted"><strong>{{ m.name }}</strong><time>{{ new Date(m.created_at).toLocaleString() }}</time></div><p class="mt-1 whitespace-pre-wrap break-words">{{ m.body }}</p></article></div><PageLinks v-if="messages" :data="messages" />
    <form v-if="thread||!admin" @submit.prevent="send" class="space-y-3"><label v-if="!thread" class="block text-sm">Send to<select v-model="form.category" class="field mt-1 w-full"><option value="technical">Technical support</option><option value="credits">Credit support</option></select></label><textarea v-model="form.body" aria-label="Message" required maxlength="4000" rows="3" class="field w-full text-sm" placeholder="Leave a private message…" /><p v-for="error in form.errors" :key="error" role="alert" class="text-sm text-error">{{ error }}</p><button class="action !py-2" :disabled="form.processing||!form.body.trim()">Send free message</button></form>
    <p v-else class="text-sm text-muted">Select a conversation to reply.</p>
   </section>
  </div>
 </main></AuthenticatedLayout>
</template>
