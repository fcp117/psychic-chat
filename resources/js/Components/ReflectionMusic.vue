<script setup>
import {computed,ref,watch,onMounted,onBeforeUnmount} from 'vue';
import {usePage} from '@inertiajs/vue3';
import axios from 'axios';
const page=usePage();
const audio=ref(null),playing=ref(false),muted=ref(true),error=ref(''),loading=ref(false),saving=ref(false);
const control=ref(null);
let waiting=false,disposed=false,timer,revision=0;
const userId=()=>page.props.auth?.user?.id??null;
function stopWaiting(){
 waiting=false;
 document.removeEventListener('click',startOnGesture,true);
 document.removeEventListener('keydown',startOnGesture,true);
}
function waitForGesture(){
 if(waiting||disposed)return;
 waiting=true;
 document.addEventListener('click',startOnGesture,true);
 document.addEventListener('keydown',startOnGesture,true);
}
function startOnGesture(event){
 if(!waiting||!event.isTrusted||loading.value||control.value?.contains(event.target))return;
 if(event.type==='keydown'&&(event.repeat||['Shift','Control','Alt','Meta','Escape'].includes(event.key)))return;
 stopWaiting();void play();
}
async function play(){
 if(!audio.value||loading.value)return false;
 const version=revision;
 loading.value=true;error.value='';
 try{
  audio.value.volume=.35;audio.value.muted=false;
  await audio.value.play();
  if(version!==revision){audio.value.muted=muted.value;return false;}
  muted.value=false;stopWaiting();return true;
 }catch(cause){
  if(version!==revision||disposed)return false;
  muted.value=true;
  if(audio.value)audio.value.muted=true;
  if(cause?.name==='NotAllowedError')waitForGesture();
  else error.value='Music could not play. Please try again.';
  return false;
 }finally{loading.value=false;}
}
function applyPreference(value){
 revision++;
 stopWaiting();muted.value=!!value;
 if(audio.value)audio.value.muted=!!value;
 if(!value&&!playing.value)waitForGesture();
}
async function toggle(){
 if(loading.value||saving.value)return;
 stopWaiting();
 const next=playing.value&&!muted.value;
 if(next){muted.value=true;audio.value.muted=true;}
 else if(!await play())return;
 if(!userId())return;
 const id=userId(),version=++revision;
 saving.value=true;
 try{
  await axios.put(route('music.update'),{muted:next});
  if(id===userId()&&version===revision)page.props.auth.user.music_muted=next;
 }catch{
  if(id===userId())error.value='Music changed here, but your preference could not be saved. Please try again.';
 }finally{saving.value=false;}
}
async function sync(){
 if(!userId()||saving.value||loading.value)return;
 const id=userId(),version=revision;
 try{
  const {data}=await axios.get(route('music.show'));
  if(id===userId()&&version===revision&&!saving.value){
   page.props.auth.user.music_muted=data.muted;
   applyPreference(data.muted);
  }
 }catch{/* Keep the current audio state when offline. */}
}
watch(userId,()=>{
 revision++;audio.value?.pause();playing.value=false;
 applyPreference(page.props.auth?.user?.music_muted??false);
});
watch(()=>page.props.auth?.user?.music_muted,value=>{
 if(userId()&&!saving.value)applyPreference(value);
});
const label=computed(()=>loading.value?'Loading music…':playing.value&&!muted.value?'Mute music':'Unmute music');
onMounted(()=>{
 applyPreference(page.props.auth?.user?.music_muted??false);
 window.addEventListener('focus',sync);
 timer=window.setInterval(sync,60000);
});
onBeforeUnmount(()=>{
 disposed=true;revision++;stopWaiting();audio.value?.pause();
 window.removeEventListener('focus',sync);window.clearInterval(timer);
});
</script>
<template>
 <div ref="control" class="flex flex-wrap items-center gap-2" aria-label="Optional reflection music">
  <audio ref="audio" src="/media/intuition-calling" preload="none" @play="playing=true" @pause="playing=false" @ended="playing=false;muted=true" @error="error='Music is temporarily unavailable.';playing=false;muted=true"></audio>
  <button type="button" :disabled="loading||saving" :aria-label="label" :title="label" :aria-pressed="playing&&!muted" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border bg-surface text-accent-text transition-colors hover:bg-accent-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50" @click="toggle">
   <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    <path d="M11 5 6 9H3v6h3l5 4Z" />
    <template v-if="playing&&!muted"><path d="M15 8a6 6 0 0 1 0 8M18 5a10 10 0 0 1 0 14" /></template>
    <path v-else d="m16 9 5 6m0-6-5 6" />
   </svg>
  </button>
  <span class="text-[10px] text-muted">Intuition Calling — © Lynn Lyric</span>
  <p v-if="error" role="alert" class="w-full text-xs text-error">{{ error }}</p>
 </div>
</template>
