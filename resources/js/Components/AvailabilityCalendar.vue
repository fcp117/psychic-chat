<script setup>
import {computed,ref,watch} from 'vue';
import {dayKey} from '@/lib/booking-calendar.mjs';
const props=defineProps({modelValue:String,slots:Array,zone:String});
const emit=defineEmits(['update:modelValue']);
const today=dayKey(Date.now(),props.zone)||new Date().toISOString().slice(0,10);
const month=ref((props.modelValue||today).slice(0,7));
watch(()=>props.modelValue,value=>{if(value)month.value=value.slice(0,7);});
const available=computed(()=>new Set(props.slots.map(s=>dayKey(s,props.zone))));
const label=computed(()=>new Intl.DateTimeFormat(undefined,{month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(month.value+'-01T12:00:00Z')));
const cells=computed(()=>{
 const first=new Date(month.value+'-01T12:00:00Z');
 const count=new Date(Date.UTC(first.getUTCFullYear(),first.getUTCMonth()+1,0)).getUTCDate();
 return [...Array(first.getUTCDay()).fill(null),...Array.from({length:count},(_,i)=>month.value+'-'+String(i+1).padStart(2,'0'))];
});
function move(n){
 const d=new Date(month.value+'-01T12:00:00Z');d.setUTCMonth(d.getUTCMonth()+n);
 month.value=d.toISOString().slice(0,7);
}
</script>
<template>
 <section class="max-w-md rounded-xl border border-border bg-page p-3" aria-label="Coach availability calendar">
  <div class="mb-2 flex items-center justify-between">
   <button type="button" class="h-9 w-9 rounded-lg hover:bg-accent-soft" aria-label="Previous month" @click="move(-1)">‹</button>
   <h3 aria-live="polite" class="text-sm font-semibold">{{label}}</h3>
   <button type="button" class="h-9 w-9 rounded-lg hover:bg-accent-soft" aria-label="Next month" @click="move(1)">›</button>
  </div>
  <div class="grid grid-cols-7 gap-1 text-center text-xs">
   <span v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d" class="py-1 text-muted">{{d}}</span>
   <template v-for="(d,i) in cells" :key="d||'empty'+i">
    <button v-if="d" type="button" class="min-h-10 rounded-lg border text-sm disabled:opacity-35" :class="modelValue===d?'border-primary bg-primary text-on-primary':available.has(d)?'border-border bg-accent-soft text-accent-text':'border-transparent'" :disabled="!available.has(d)" :aria-label="d+(available.has(d)?', available':', unavailable')" :aria-pressed="modelValue===d" @click="emit('update:modelValue',d)">{{Number(d.slice(-2))}}</button>
    <span v-else />
   </template>
  </div>
  <p class="mt-2 text-xs text-muted">Highlighted dates have open times. Booked and blocked times are excluded. {{zone}}</p>
 </section>
</template>
