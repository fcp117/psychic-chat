<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
const props=defineProps({purchase:Object,paymentError:String});
const status=ref(props.purchase.status);
const paid=ref(props.purchase.status==='paid'),checking=ref(false),error=ref(props.paymentError||''),message=ref(''); let timer,attempts=0;
const pesos=cents=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(cents/100);
const verify=async()=>{if(checking.value||paid.value)return;checking.value=true;error.value='';try{const {data}=await axios.post(route('credits.verify',props.purchase.id));paid.value=data.paid;status.value=data.status;message.value=data.paid?'Your credits have been added.':'Payment is not confirmed yet. If you just paid, please wait a moment and check again.';}catch(e){error.value=e.response?.data?.message||'Could not check payment yet.';}finally{checking.value=false;}};
onMounted(()=>{if(props.purchase.status==='pending'){verify();timer=setInterval(()=>{if(paid.value||status.value!=='pending'||++attempts>=12){clearInterval(timer);return;}verify();},10000);}});
onUnmounted(()=>clearInterval(timer));
</script>
<template><Head title="Credit purchase" /><AuthenticatedLayout><main class="mx-auto max-w-xl px-5 py-12"><Link :href="route('credits')" class="text-sm text-accent-text">← Credits</Link><h1 class="mt-5 font-serif text-3xl">{{ paid ? 'Credits added' : 'Your credit purchase' }}</h1><p class="mt-3 text-sm text-muted">Sandbox payment · no real money transferred</p><section class="mt-6 space-y-4 rounded-2xl border border-border bg-surface p-6"><h2 class="font-semibold">{{ purchase.label }}</h2><div class="flex justify-between"><span>{{ purchase.credits }} credits</span><strong>{{ pesos(purchase.amount) }}</strong></div><p class="text-sm capitalize text-muted">{{ purchase.provider }} · {{ paid ? 'Paid' : status.replaceAll('_',' ') }}</p><p v-if="message" role="status" class="text-sm text-accent-text">{{ message }}</p><p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p><div v-if="!paid && status==='pending'" class="flex flex-wrap items-center gap-4"><button class="action" :disabled="checking" @click="verify">{{ checking ? 'Checking…' : 'Check payment' }}</button><a v-if="purchase.checkout_url" :href="purchase.checkout_url" class="text-sm text-accent-text underline">Return to checkout</a></div><Link v-if="paid" :href="route('credits')" class="action inline-block">View balance</Link><p v-if="purchase.status==='setup_failed'" class="text-sm text-muted">Return to Credits to choose a payment method again.</p></section></main></AuthenticatedLayout></template>
