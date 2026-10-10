<script setup>
import {computed,ref,onMounted,onBeforeUnmount} from 'vue';
const audio=ref(null),playing=ref(false),muted=ref(true),error=ref(''),loading=ref(false);
const control=ref(null);
let waitingForGesture=false;
let disposed=false;
function stopWaiting(){
 waitingForGesture=false;
 document.removeEventListener('click',startOnGesture,true);
 document.removeEventListener('keydown',startOnGesture,true);
}
function startOnGesture(event){
 // Let the music button handle its own gesture exactly once.
 if(!waitingForGesture||!event.isTrusted||loading.value||control.value?.contains(event.target))return;
 if(event.type==='keydown'&&(event.repeat||['Shift','Control','Alt','Meta','Escape'].includes(event.key)))return;
 stopWaiting();
 void toggle(true);
}
function waitForGesture(){
 if(waitingForGesture||disposed)return;
 waitingForGesture=true;
 document.addEventListener('click',startOnGesture,true);
 document.addEventListener('keydown',startOnGesture,true);
}
const label=computed(()=>loading.value?'Loading music…':playing.value&&!muted.value?'Mute music':'Unmute music');
async function toggle(automatic=false){
 if(!audio.value||loading.value)return;
 if(!automatic)stopWaiting();
 error.value='';
 if(playing.value){
  muted.value=!muted.value;
  audio.value.muted=muted.value;
  return;
 }
 loading.value=true;
 try{
  audio.value.volume=.35;
  audio.value.muted=false;
  await audio.value.play();
  stopWaiting();
  muted.value=false;
 }catch(cause){
  muted.value=true;
  audio.value.muted=true;
  // Browser autoplay policy is expected; keep the button available for a user gesture.
  error.value=automatic&&cause?.name==='NotAllowedError'?'':'Music could not play. Please try again.';
  if(automatic&&cause?.name==='NotAllowedError')waitForGesture();
 }finally{loading.value=false;}
}
onMounted(()=>{toggle(true);});
onBeforeUnmount(()=>{disposed=true;stopWaiting();audio.value?.pause();});
</script>
<template>
 <div ref="control" class="flex flex-wrap items-center gap-2" aria-label="Optional reflection music">
  <audio ref="audio" src="/media/intuition-calling" preload="none" @play="playing=true" @pause="playing=false" @ended="playing=false;muted=true" @error="error='Music is temporarily unavailable.';playing=false;muted=true"></audio>
  <button type="button" :disabled="loading" :aria-label="label" :title="label" :aria-pressed="playing&&!muted" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border bg-surface text-accent-text transition-colors hover:bg-accent-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50" @click="toggle(false)">
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
