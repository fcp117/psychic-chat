<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import axios from 'axios';
const page=usePage(), enabled=ref(false), requests=ref([]), error=ref('');
let audio,timer,busy=false,stopped=false;
const storageKey=()=>`reading-alerts:${page.props.auth.user.id}`;
const seen=()=>{try{return new Set(JSON.parse(localStorage.getItem(storageKey())||'[]'));}catch{return new Set();}};
const beep=()=>{if(!audio||audio.state!=='running')return;const osc=audio.createOscillator(),gain=audio.createGain();osc.connect(gain);gain.connect(audio.destination);osc.frequency.value=660;gain.gain.setValueAtTime(0.08,audio.currentTime);gain.gain.exponentialRampToValueAtTime(0.001,audio.currentTime+0.45);osc.start();osc.stop(audio.currentTime+0.45);};
const refresh=async()=>{if(busy||stopped)return;busy=true;try{const {data}=await axios.get(route('chat.requests'));if(stopped)return;requests.value=data.requests;if(enabled.value){const ids=seen();const fresh=requests.value.filter(r=>!ids.has(r.id));if(fresh.length&&audio?.state==='running'){beep();fresh.forEach(r=>ids.add(r.id));try{localStorage.setItem(storageKey(),JSON.stringify([...ids].slice(-1000)));}catch{}}}}catch{/* Keep the visual notifications available. */}finally{busy=false;}};
const toggle=async()=>{if(enabled.value){enabled.value=false;return;}try{audio ||= new (window.AudioContext||window.webkitAudioContext)();await audio.resume();enabled.value=true;error.value='';beep();refresh();}catch{error.value='Sound unavailable in this browser. Visual notifications still work.';}};
onMounted(()=>{refresh();timer=setInterval(refresh,10000);});
onUnmounted(()=>{stopped=true;clearInterval(timer);audio?.close();});
</script>
<template><div class="flex items-center gap-2 text-xs"><button type="button" :aria-pressed="enabled" class="text-accent-text underline" @click="toggle">{{ enabled?'Mute alerts':'Enable sound' }}</button><Link v-if="requests.length" :href="route('chat.room',requests[0].conversationId)" class="font-semibold text-accent-text">{{ requests.length }} request{{ requests.length===1?'':'s' }}</Link><span v-if="error" role="status">{{ error }}</span></div></template>
