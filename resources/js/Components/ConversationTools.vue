<script setup>
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
const props=defineProps({session:Object,coach:Boolean,conversationId:Number});
const emit=defineEmits(['refresh','message']);
const panel=ref(''),note=ref(''),transcript=ref(''),busy=ref(false),error=ref(''),notice=ref('');
const consent=ref(false),file=ref(null),fileInput=ref(null);let uploadKey=null;
const confirm=useForm({consent:true});
watch(()=>props.session.invitation?.id,()=>{consent.value=false;confirm.clearErrors();});
const available=computed(()=>props.session.coach_available!==false);
const open=async name=>{panel.value=name;error.value='';notice.value='';busy.value=true;try {if(name==='notes')note.value=(await axios.get(route('conversation.notes',props.conversationId))).data.body;else transcript.value=(await axios.get(route('conversation.transcript',props.conversationId))).data.text;}catch{error.value='Could not load this. Please retry.';}finally{busy.value=false;}};
const save=async()=>{busy.value=true;error.value='';try{await axios.put(route('conversation.notes.save',props.conversationId),{body:note.value});notice.value='Private notes saved.';}catch{error.value='Could not save notes. Your text is still here.';}finally{busy.value=false;}};
const copy=async()=>{try{await navigator.clipboard.writeText(transcript.value);notice.value='Copied. Keep exported conversations private.';}catch{error.value='Copy was unavailable. Select the text below or use Download.';}};
const invite=async()=>{busy.value=true;error.value='';try{await axios.post(route('conversation.invite',props.conversationId));emit('refresh');notice.value='Invitation sent. No billing starts until the client confirms and you accept.';}catch(e){error.value=e.response?.data?.message||'Unable to send invitation.';}finally{busy.value=false;}};
const upload=async()=>{if(!file.value||busy.value)return;busy.value=true;error.value='';const data=new FormData();data.append('attachment',file.value);data.append('request_key',uploadKey);data.append('content','');try{const result=await axios.post(route('chat.message',props.session.id),data);emit('message',result.data.message);file.value=null;fileInput.value.value='';uploadKey=null;}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||'Upload failed. Please retry.';}finally{busy.value=false;}};
const choose=e=>{file.value=e.target.files[0]||null;uploadKey=crypto.randomUUID();if(file.value?.size>5*1024*1024){error.value='Choose a file no larger than 5 MB.';file.value=null;}};
</script>
<template>
 <div class="border-x border-border bg-surface px-3 py-2 text-xs">
  <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
   <button type="button" class="text-accent-text underline" @click="open('transcript')">Transcript</button>
   <button v-if="coach" type="button" class="text-accent-text underline" @click="open('notes')">Private notes</button>
   <label class="cursor-pointer text-accent-text underline">Attach image/PDF<input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" @change="choose" /></label>
   <button v-if="coach && available && !['active','pending'].includes(session.status)" type="button" :disabled="busy" class="font-semibold text-accent-text" @click="invite">Invite to paid reading</button>
   <Link :href="route('support.index')" class="text-muted underline">Technical / credit support</Link>
  </div>
  <div v-if="file" class="mt-2 flex items-center gap-2"><span class="truncate">{{ file.name }} · private attachment</span><button type="button" class="font-semibold underline" :disabled="busy" @click="upload">{{ busy?'Uploading…':'Send file' }}</button><button type="button" @click="file=null">Cancel</button></div>
  <div v-if="session.invitation && available && !['active','pending'].includes(session.status)" class="mt-2 rounded-lg bg-accent-soft p-3">
   <p class="font-semibold">Paid reading invitation · 1 minute per reading minute</p><p class="mt-1">Expires {{ new Date(session.invitation.expires_at).toLocaleString() }}. Confirming requests a reading; billing starts only after coach acceptance while you are connected.</p>
   <template v-if="!coach"><label class="mt-2 flex items-center gap-2"><input v-model="consent" type="checkbox" />I agree to use my minutes, rounded up once when the reading ends.</label><button type="button" class="action mt-2 !px-3 !py-1.5 text-xs" :disabled="!consent||confirm.processing" @click="confirm.post(route('conversation.confirm',session.invitation.id),{preserveScroll:true,onSuccess:()=>{consent=false;emit('refresh')}})">Confirm reading</button><p v-for="message in confirm.errors" :key="message" role="alert" class="mt-1 text-error">{{ message }}</p></template>
  </div>
  <p v-if="error" role="alert" class="mt-2 text-error">{{ error }}</p><p v-if="notice" role="status" class="mt-2 text-accent-text">{{ notice }}</p>
 </div>
 <Modal :show="!!panel" max-width="lg" @close="panel=''">
  <section class="space-y-3 p-5"><div class="flex justify-between gap-3"><h2 class="font-serif text-xl">{{ panel==='notes'?'Private coach notes':'Conversation transcript' }}</h2><button aria-label="Close" @click="panel=''">×</button></div>
   <p class="text-xs text-muted">{{ panel==='notes'?'Only you can view these notes. They are never included in messages or transcripts.':'Includes free messages and paid readings. Attachments are listed by name; private notes are excluded. Times are UTC. Keep exported copies private.' }}</p>
   <p v-if="busy" role="status" class="text-sm">Loading/saving…</p>
   <textarea v-if="panel==='notes'" v-model="note" rows="6" maxlength="20000" aria-label="Private notes" class="field w-full text-sm" />
   <textarea v-else :value="transcript" readonly rows="10" aria-label="Transcript" class="field w-full text-xs" />
   <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p><p v-if="notice" role="status" class="text-xs text-accent-text">{{ notice }}</p>
   <div class="flex justify-end gap-3"><button v-if="panel==='notes'" class="action !py-2" :disabled="busy" @click="save">Save notes</button><template v-else><button :disabled="busy" class="text-sm underline" @click="copy">Copy</button><a :href="route('conversation.transcript',{s:conversationId,download:1})" class="action !py-2 text-sm">Download .txt</a></template></div>
  </section>
 </Modal>
</template>
