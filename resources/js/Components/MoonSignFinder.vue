<script setup>
import {ref} from 'vue';
import {findMoonSign} from '@/lib/moonoscope.mjs';
const props=defineProps({birthdate:String});
const emit=defineEmits(['select']);
const date=ref(props.birthdate||''),time=ref(''),unknown=ref(false),city=ref(''),zone=ref('Asia/Manila'),result=ref(null),error=ref('');
const zones=Intl.supportedValuesOf?Intl.supportedValuesOf('timeZone'):['Asia/Manila','Asia/Singapore','Australia/Perth','Europe/Rome','America/New_York','Europe/London','UTC'];
const cities={'manila':'Asia/Manila','quezon city':'Asia/Manila','cebu':'Asia/Manila','davao':'Asia/Manila','baguio':'Asia/Manila','iloilo':'Asia/Manila','perth':'Australia/Perth','rome':'Europe/Rome','new york':'America/New_York','london':'Europe/London','singapore':'Asia/Singapore','sydney':'Australia/Sydney','tokyo':'Asia/Tokyo'};
const recognized=ref(false);
function cityChanged(){const z=cities[city.value.trim().toLowerCase()];recognized.value=!!z;if(z)zone.value=z;result.value=null;}
function calculate(){error.value='';result.value=null;try{if(Date.parse(date.value+'T00:00:00Z')>Date.now())throw new Error('Birthdate cannot be in the future.');if(!unknown.value&&!time.value)throw new Error('Enter a birth time or select unknown.');result.value=findMoonSign(date.value,unknown.value?'':time.value,zone.value);if(result.value.signs.length===1)emit('select',result.value.signs[0]);}catch(e){error.value=e.message;}}
function local(utc){return new Date(utc).toLocaleString(undefined,{timeZone:zone.value,dateStyle:'medium',timeStyle:'short'});}
</script>
<template>
 <section class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
  <p class="text-xs uppercase tracking-widest text-accent-text">Discover your moon sign</p><h2 class="mt-2 font-serif text-2xl">Your quieter light within.</h2>
  <p class="mt-2 text-sm leading-6 text-muted">Enter your birthday, birth time, and city of birth. Use the time on your birth certificate where possible.</p>
  <form class="mt-4 space-y-3" @submit.prevent="calculate">
   <label class="block text-sm">Birthday<input v-model="date" required type="date" min="1900-01-01" class="field mt-1 w-full" @input="result=null" /></label>
   <label class="flex items-center gap-2 text-sm"><input v-model="unknown" type="checkbox" @change="result=null" />I don't know my birth time</label>
   <label v-if="!unknown" class="block text-sm">Birth time<input v-model="time" type="time" required class="field mt-1 w-full" @input="result=null" /></label>
   <label class="block text-sm">City of birth<input v-model="city" class="field mt-1 w-full" placeholder="e.g. Manila" maxlength="100" @input="cityChanged" /></label>
   <p class="text-xs text-muted">{{ recognized?'Suggested time zone selected. Please confirm it below.':'Choose the birth time zone below if your city is not recognized. Philippine cities use Asia/Manila.' }}</p>
   <label class="block text-sm">Birth time zone<select v-model="zone" class="field mt-1 w-full" @change="result=null"><option v-for="z in zones" :key="z">{{ z }}</option></select></label>
   <p class="text-xs leading-5 text-muted">Your account birthday is prefilled when available. This finder runs in your browser; birth time and city are not submitted or saved. Historical time-zone rules are used.</p>
   <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p><button class="action w-full">Find my Moon sign</button>
  </form>
  <div v-if="result" role="status" class="mt-4 rounded-xl border border-border bg-page p-4">
   <p class="font-serif text-xl">{{ result.signs.join(' or ') }} Moon</p>
   <p class="mt-2 text-sm text-muted" v-if="result.unknown">{{ result.signs.length>1?'The Moon changed signs that day. Without a birth time, either sign is possible.':'The Moon remained in this sign throughout your birth date in the selected time zone.' }}</p>
   <p v-if="result.ambiguousTime" class="mt-2 text-sm text-muted">This clock time occurred twice during a time-zone change. Both possible instants were checked; confirm your birth record.</p>
   <p v-for="p in result.positions" :key="p.utc" class="mt-2 text-xs text-muted">{{ p.degree }}° within sign · {{ p.utc }}</p>
   <p v-for="c in result.changes" :key="c.utc" class="mt-2 text-xs text-muted">{{ c.from }} → {{ c.to }} at approximately {{ local(c.utc) }} ({{ zone }}). Near this boundary, a small birth-time difference can change the result.</p>
   <p v-if="result.signs.length>1" class="mt-2 text-xs text-muted">You can explore either sign's reflection; neither is confirmed.</p>
  </div>
  <p class="mt-4 text-[11px] text-muted">Calculated with Astronomy Engine · tropical, geocentric Moon · whole-sign houses for daily reflections.</p>
 </section>
</template>
