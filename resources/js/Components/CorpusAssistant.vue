<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page=usePage(), open=ref(false), prompt=ref(''), sending=ref(false), error=ref('');
const enabled=computed(()=>Boolean(page.props.assistant?.enabled));
const signedIn=computed(()=>Boolean(page.props.auth?.user));
const userId=computed(()=>page.props.auth?.user?.id ?? null);
const visible=computed(()=>page.component!=='Error');
const name=computed(()=>page.props.auth?.user?.name?.split(' ')[0]||'there');
const sharedUsage=page.props.assistant?.usage;
const guideState=window.__intuitionIslandGuideState||(window.__intuitionIslandGuideState={
 messages:ref([{role:'assistant',content:`Hi ${page.props.auth?.user?.name?.split(' ')[0]||'there'} — I’m Isla, Intuition Island’s virtual guide. I can explain how the website works or share general information from the approved library. I can’t give personal readings or predictions.`,sources:[],welcome:true}]),
 usage:ref({
  website:{...(sharedUsage?.website||{used:0,limit:25})},
  library:{...(sharedUsage?.library||{used:0,limit:5})},
 }),
});
const messages=guideState.messages;
const usage=guideState.usage;
const websiteSuggestions=['How do I find a spiritual advisor?','When does billing start?','How do credits work?'];
const librarySuggestions=['What is a psychic?','What are the five Clairs?'];
let autoOpenTimer;
onMounted(()=>{
 const currentUser=String(userId.value||'');
 if(signedIn.value&&enabled.value&&window.__intuitionIslandIslaAutoOpenedForUser!==currentUser){
  autoOpenTimer=window.setTimeout(()=>{
   open.value=true;
   window.__intuitionIslandIslaAutoOpenedForUser=currentUser;
  },3000);
 }
});
onUnmounted(()=>window.clearTimeout(autoOpenTimer));
const ask=async (question = '')=>{
 const message=(question||prompt.value).trim(); if(!message||sending.value||!enabled.value)return;
 if(!signedIn.value){error.value='Please sign in and verify your email to use Isla.';return;}
 const history=messages.value.slice(-4).map(({role,content})=>({role,content}));
 messages.value.push({role:'user',content:message});prompt.value='';sending.value=true;error.value='';
 try{const {data}=await axios.post(route('assistant.respond'),{message,history});messages.value.push({role:'assistant',content:data.reply,sources:data.sources||[]});if(data.usage)usage.value[data.usage.category]={used:data.usage.used,limit:data.usage.limit};}
 catch(e){messages.value.pop();error.value=e.response?.data?.message||'The site guide is unavailable right now.';}
 finally{sending.value=false;}
};
</script>
<template>
 <div v-if="visible" class="fixed bottom-5 right-3 z-30 sm:right-5">
  <section v-if="open" class="mb-3 flex max-h-[32rem] w-[min(23rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-2xl">
   <header class="flex items-center justify-between gap-3 bg-accent-soft p-4"><div><h2 class="text-sm font-semibold">Isla · virtual guide</h2><p class="mt-1 text-xs text-muted">Free website help & educational library</p></div><button class="text-xl" aria-label="Close guide" @click="open=false">×</button></header>
   <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4"><div v-for="(item,index) in messages" :key="index" class="flex" :class="item.role==='user'?'justify-end':'justify-start'"><div class="max-w-[88%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-6" :class="item.role==='user'?'bg-primary text-on-primary':'bg-accent-soft text-content'"><p v-if="item.role==='assistant'" class="mb-1 text-xs font-semibold text-accent-text">Isla</p>{{ item.content }}<template v-if="item.welcome"><p v-if="!signedIn" class="mt-2">Sign in and verify your email to ask Isla a question.</p><p class="mt-3 text-xs font-semibold uppercase tracking-wide text-accent-text">Website help</p><div class="mt-2 flex flex-wrap gap-2 whitespace-normal"><button v-for="question in websiteSuggestions" :key="question" type="button" class="rounded-full border border-border px-3 py-1.5 text-left text-xs text-accent-text hover:bg-surface-hover" :disabled="sending" @click="ask(question)">{{ question }}</button></div><p class="mt-3 text-xs font-semibold uppercase tracking-wide text-accent-text">Educational library · {{ usage.library.used }}/{{ usage.library.limit }} today</p><div class="mt-2 flex flex-wrap gap-2 whitespace-normal"><button v-for="question in librarySuggestions" :key="question" type="button" class="rounded-full border border-border px-3 py-1.5 text-left text-xs text-accent-text hover:bg-surface-hover" :disabled="sending || usage.library.used >= usage.library.limit" @click="ask(question)">{{ question }}</button></div><Link v-if="signedIn" :href="route('psychics.index')" class="mt-3 inline-block text-accent-text underline">Find a Spiritual Advisor →</Link><Link v-else :href="route('login')" class="mt-3 inline-block text-accent-text underline">Sign in →</Link></template><p v-if="item.sources?.length" class="mt-2 text-xs text-muted">Sources: {{ item.sources.join(' · ') }}</p></div></div><p v-if="sending" class="text-sm text-muted">Finding a safe answer…</p><p v-if="error" class="text-sm text-error">{{ error }}</p></div>
   <form class="border-t border-border p-4" @submit.prevent="ask()"><textarea v-model="prompt" rows="2" maxlength="600" class="field w-full resize-none text-sm" :disabled="!enabled||sending" :placeholder="enabled?'Ask about the website or library…':'The virtual guide is being configured…'"/><div class="mt-2 flex items-center justify-between gap-3"><p class="text-xs text-muted">Library questions: {{ usage.library.used }}/{{ usage.library.limit }} today</p><button class="action !px-4 !py-2 text-sm" :disabled="!enabled||sending||!prompt.trim()">Send</button></div></form>
  </section>
  <button type="button" class="ml-auto flex h-14 items-center gap-2 rounded-full bg-primary px-5 text-base font-semibold text-on-primary shadow-xl shadow-primary/30 transition hover:-translate-y-0.5 hover:bg-primary-hover" :aria-expanded="open" @click="open=!open">✧ {{ open?'Close Isla':'Ask Isla' }}</button>
 </div>
</template>
