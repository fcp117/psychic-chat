<script setup>
import {computed,ref} from 'vue';
import {Head,useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
import {dayKey} from '@/lib/booking-calendar.mjs';
const props=defineProps({coaches:Array,windows:Array,bookings:Array,filters:Object});
const filter=useForm({...props.filters}),date=ref(props.filters.month+'-01');
const events=computed(()=>[...props.windows.map(w=>({...w,key:'w'+w.id,label:w.is_available?'Available':'Time off'})),...props.bookings.map(b=>({...b,key:'b'+b.id,label:'Booked'}))]);
const onDay=d=>events.value.filter(e=>dayKey(e.starts_at,'Asia/Manila')<=d&&dayKey(new Date(Date.parse(e.ends_at)-1),'Asia/Manila')>=d);
const cells=computed(()=>{const first=new Date(props.filters.month+'-01T12:00:00Z');const count=new Date(Date.UTC(first.getUTCFullYear(),first.getUTCMonth()+1,0)).getUTCDate();return [...Array(first.getUTCDay()).fill(null),...Array.from({length:count},(_,i)=>props.filters.month+'-'+String(i+1).padStart(2,'0'))];});
const coachName=id=>props.coaches.find(c=>c.id===id)?.name||'Coach';
const fmt=value=>new Intl.DateTimeFormat(undefined,{timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'}).format(new Date(value));
</script>
<template><Head title="Coach calendar" /><AuthenticatedLayout><AdminWorkspace section="schedule">
 <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="filter.get(route('staff.schedule'))"><label class="text-xs">Month<input v-model="filter.month" type="month" required class="field mt-1 block" /></label><label class="text-xs">Coach<select v-model="filter.coach" class="field mt-1 block"><option value="">Every coach</option><option v-for="c in coaches" :key="c.id" :value="c.id">{{c.name}}{{c.is_suspended?' · suspended':''}}</option></select></label><button class="action !py-2">View calendar</button></form>
 <p class="mb-3 text-xs text-muted">Asia/Manila · Read-only · Available hours, time off, and reserved appointments. Client identities are not shown.</p>
 <div class="grid grid-cols-7 gap-1 rounded-xl border border-border bg-page p-2 text-center"><span v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d" class="text-xs text-muted">{{d}}</span><template v-for="(d,i) in cells" :key="d||i"><button v-if="d" type="button" :aria-label="d" :aria-pressed="date===d" @click="date=d" class="min-h-16 min-w-0 rounded-lg border p-1 text-sm sm:min-h-20" :class="date===d?'border-primary bg-accent-soft':'border-border bg-surface'">{{Number(d.slice(-2))}}<span class="mt-1 flex justify-center gap-1"><span v-if="onDay(d).some(e=>e.label==='Available')" class="h-1.5 w-1.5 rounded-full bg-success" /><span v-if="onDay(d).some(e=>e.label==='Time off')" class="h-1.5 w-1.5 rounded-full bg-danger" /><span v-if="onDay(d).some(e=>e.label==='Booked')" class="h-1.5 w-1.5 rounded-full bg-primary" /></span></button><span v-else /></template></div>
 <p class="my-3 text-xs text-muted">Green: available hours · Red: time off · Purple: booked. Time off and bookings override available hours.</p>
 <h3 class="mb-2 font-serif text-xl">{{date}}</h3><div class="max-h-80 space-y-2 overflow-y-auto"><article v-for="e in onDay(date)" :key="e.key" class="rounded-xl border border-border bg-surface p-3 text-sm"><strong>{{coachName(e.coach_id)}}</strong> · {{e.label}}<p class="mt-1 text-xs text-muted">{{fmt(e.starts_at)}} — {{fmt(e.ends_at)}}</p></article><p v-if="!onDay(date).length" class="text-sm text-muted">No schedule entries for this date.</p></div>
</AdminWorkspace></AuthenticatedLayout></template>
