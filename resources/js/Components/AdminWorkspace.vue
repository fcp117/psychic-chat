<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const props=defineProps({ section: { type:String, default:'users' } });
const page=usePage();
const sections=computed(()=>[
 ['users','Accounts','Users & Spiritual Coaches'],['applications','Applications','Review coach applications'],
 ['reports','Reports & access','Review reports and manage blocks'],['health','System health','Monitor site incidents'],
 ['pricing','Pricing & billing','Rates and credit packages'],['transactions','Credit transactions','Review balance changes'],
 ['sessions','Chat sessions','Manage readings'],['forecasts','Forecasts','Manage published forecasts'],['audit','Audit history','Track administrative changes'],
].filter(([key])=>key!=='forecasts'||page.props.features?.forecast));
const active=computed(()=>sections.value.find(([key])=>key===props.section)||sections.value[0]);
</script>
<template>
 <main class="mx-auto max-w-[90rem] px-3 py-6 sm:px-6">
  <div class="overflow-hidden rounded-3xl border border-border bg-surface/95 shadow-xl backdrop-blur-xl lg:grid lg:grid-cols-[14rem_minmax(0,1fr)]">
   <aside class="border-b border-border bg-page/70 p-4 lg:border-b-0 lg:border-r">
    <div class="px-2 pb-4 pt-2"><p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-accent-text">Administration</p><h1 class="mt-2 font-serif text-2xl">Admin Settings</h1></div>
    <nav aria-label="Admin sections" class="flex gap-1 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
     <Link v-for="[key,label] in sections" :key="key" :href="key==='reports'?route('admin.reports'):route('admin.settings',{section:key})" :aria-current="section===key?'page':undefined" class="flex min-h-11 shrink-0 items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" :class="section===key?'bg-accent-soft text-accent-text':'text-muted hover:bg-surface-hover hover:text-content'"><span>{{ label }}</span><span v-if="section===key" class="h-1.5 w-1.5 rounded-full bg-primary" aria-hidden="true"></span></Link>
    </nav>
   </aside>
   <div class="min-w-0">
    <header class="border-b border-border bg-surface px-5 py-5 sm:px-7"><p class="text-[10px] font-semibold uppercase tracking-widest text-muted">Workspace / {{ active[1] }}</p><h2 class="mt-2 font-serif text-3xl text-content">{{ active[1] }}</h2><p class="mt-1 text-sm text-muted">{{ active[2] }}</p></header>
    <div class="min-w-0 p-4 sm:p-6"><slot /></div>
   </div>
  </div>
 </main>
</template>
