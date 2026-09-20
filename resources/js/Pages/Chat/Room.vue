<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue';
import axios from 'axios';
const props = defineProps({ session: Object, conversationId: Number, initialMessages: Array, currentUser: Object });
const session = ref(props.session), balance = ref(props.currentUser.available_credits);
const messages = ref([...props.initialMessages]), newMessage = ref(''), sending = ref(false);
const error = ref(''), connectionError = ref(false), container = ref(null), action = useForm({});
const agreement = ref(null), showAgreement = ref(false), loadingAgreement = ref(false);
const request = useForm({accepted_rate: 0, consent: true, hide_notice: false});
const isCounselor = computed(() => props.currentUser.id === session.value.counselor_id);
const partner = computed(() => isCounselor.value ? session.value.client : session.value.counselor);
const live = computed(() => session.value.status === 'active');
const pending = computed(() => session.value.status === 'pending');
const now = ref(Date.now()); let serverOffset = 0;
const present = value => value && now.value - Date.parse(value) < (session.value.disconnect_seconds || 30) * 1000;
const ownSeen = computed(() => isCounselor.value ? session.value.counselor_seen_at : session.value.client_seen_at);
const partnerSeen = computed(() => isCounselor.value ? session.value.client_seen_at : session.value.counselor_seen_at);
let timer, clock, polling = false, stopped = false, lastActivity = Date.now();
const markActivity = () => { if (!document.hidden) lastActivity = Date.now(); };
const visible = () => { if (!document.hidden) { markActivity(); heartbeat(); } };
const scroll = async () => { await nextTick(); if (container.value) container.value.scrollTop = container.value.scrollHeight; };
const append = message => { if (!messages.value.some(m => m.id === message.id)) { messages.value.push(message); messages.value.sort((a,b)=>a.id-b.id); scroll(); } };
watch(() => props.session, value => { session.value = value; heartbeat(); });
watch(() => props.initialMessages, value => value.forEach(append));
const heartbeat = async () => {
    if (polling || stopped) return;
    polling = true;
    try {
        const result = await axios.post(route('chat.heartbeat', props.conversationId), {
            last_message_id: messages.value.at(-1)?.id ?? 0,
            active: !document.hidden && Date.now() - lastActivity < (session.value.disconnect_seconds || 30) * 1000,
            idle_seconds: Math.max(0, Math.floor((Date.now() - lastActivity) / 1000)),
        });
        if (!stopped) { session.value = result.data.session; balance.value = result.data.balance; serverOffset = Date.parse(result.data.server_time)-Date.now(); connectionError.value = false; result.data.messages.forEach(append); }
    } catch { if (!stopped) connectionError.value = true; }
    finally { polling = false; }
};
onMounted(() => {
    scroll(); heartbeat(); timer = setInterval(heartbeat, 5000); clock = setInterval(() => { now.value = Date.now()+serverOffset; }, 1000);
    ['pointerdown','pointermove','keydown','touchstart','scroll'].forEach(event => window.addEventListener(event, markActivity, {passive:true,capture:true}));
    document.addEventListener('visibilitychange',visible);
    window.Echo?.private(`chat.${props.conversationId}`).listen('.MessageSent', event => append(event.message)).listen('.ReadingUpdated', heartbeat);
});
onUnmounted(() => {
    stopped = true; clearInterval(timer); clearInterval(clock); window.Echo?.leave(`chat.${props.conversationId}`);
    ['pointerdown','pointermove','keydown','touchstart','scroll'].forEach(event => window.removeEventListener(event,markActivity,true));
    document.removeEventListener('visibilitychange',visible);
});
const continueChat = async () => {
    if (loadingAgreement.value || request.processing) return;
    loadingAgreement.value=true; error.value=''; request.clearErrors();
    try {
        const result = await axios.get(route('chat.agreement',props.conversationId)); agreement.value=result.data;
        request.accepted_rate=result.data.rate; request.hide_notice=false; request.consent=!!result.data.show_notice;
        if (result.data.show_notice) showAgreement.value=true; else submitRequest();
    } catch { error.value='Could not load the current rate. Please retry.'; }
    finally { loadingAgreement.value=false; }
};
const submitRequest = () => request.post(route('psychics.chat',session.value.counselor_id), {preserveScroll:true,onSuccess:() => { showAgreement.value=false; markActivity(); heartbeat(); }});
const send = async () => {
    if (sending.value || !newMessage.value.trim() || !live.value) return;
    const content=newMessage.value; sending.value=true; error.value=''; markActivity();
    try { const result=await axios.post(route('chat.message',session.value.id),{content},{headers:{'X-Socket-ID':window.Echo?.socketId() ?? ''}}); append(result.data.message); newMessage.value=''; }
    catch(e) { error.value=e.response?.data?.errors?.reading?.[0] || e.response?.data?.message || 'Message could not be sent. Please retry.'; heartbeat(); }
    finally { sending.value=false; }
};
</script>
<template>
    <Head :title="partner?.name || 'Conversation'" />
    <main class="mx-auto flex h-[100dvh] max-w-4xl flex-col gap-3 px-3 py-3 sm:px-6 sm:py-5">
        <header class="shrink-0 rounded-2xl border border-border bg-surface p-4">
            <Link :href="route('chat.index')" class="text-xs text-accent-text">← Conversations</Link>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2"><h1 class="font-serif text-xl sm:text-2xl">{{ partner?.name || 'Your conversation' }}</h1><span class="rounded-full bg-accent-soft px-3 py-1 text-xs text-accent-text">{{ live ? 'Reading active' : pending ? 'Request pending' : 'Reading inactive' }}</span></div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted"><span><span :class="present(partnerSeen) ? 'text-success' : 'text-muted'">●</span> {{ partner?.name }} · {{ present(partnerSeen) ? 'Active' : 'Inactive' }}</span><span><span :class="present(ownSeen) ? 'text-success' : 'text-muted'">●</span> You · {{ present(ownSeen) ? 'Active' : 'Inactive' }}</span></div>
            <p v-if="live" class="mt-2 text-xs text-muted">{{ session.agreed_rate }} credits/hour · Reading stops after {{ session.disconnect_seconds }} seconds without interaction.</p>
            <div v-if="pending || live" class="mt-3 flex flex-wrap items-center gap-3"><button v-if="isCounselor && pending" class="action !px-4 !py-2" :disabled="action.processing" @click="action.post(route('chat.accept',session.id))">Accept & continue</button><p v-if="pending && !isCounselor" class="text-xs text-muted" role="status">Request sent. Waiting for acceptance; no charge yet.</p><button class="text-xs text-muted underline" :disabled="action.processing" @click="action.post(route('chat.end',session.id))">{{ pending ? 'Cancel request' : 'End reading' }}</button></div>
        </header>
        <p v-if="connectionError" role="alert" class="text-xs text-error">Reconnecting… Billing stops if the connection remains inactive.</p>
        <div ref="container" role="log" aria-label="Conversation messages" aria-live="polite" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain rounded-2xl border border-border bg-page p-3 sm:p-5">
            <p v-if="!messages.length" class="py-8 text-center text-sm text-muted">Your messages stay here across readings.</p>
            <template v-for="msg in messages" :key="msg.id"><div v-if="msg.kind === 'system'" class="mx-auto max-w-md rounded-xl bg-accent-soft px-4 py-3 text-center text-xs leading-5 text-accent-text"><span class="block font-medium">Psychic Chat</span>{{ msg.content }}</div><div v-else class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm" :class="msg.sender_id === currentUser.id ? 'self-end bg-primary text-on-primary' : 'self-start bg-surface text-content'"><p class="mb-1 text-xs opacity-75">{{ msg.sender?.name }}</p>{{ msg.content }}</div></template>
            <div v-if="!live && !pending" class="mx-auto max-w-md rounded-xl border border-border bg-surface p-4 text-center text-sm"><p class="text-muted">This reading is inactive. Your conversation stays here.</p><button v-if="!isCounselor" class="mt-2 font-semibold text-accent-text underline" :disabled="loadingAgreement || request.processing" @click="continueChat">Click here to request to continue</button><p v-else class="mt-2 text-xs text-muted">You’ll receive a notification when the user requests to continue.</p></div>
        </div>
        <footer class="shrink-0 pb-[env(safe-area-inset-bottom)]">
            <p v-if="!isCounselor" class="mb-2 px-1 text-xs text-muted"><span class="font-medium text-content">{{ Number(balance).toLocaleString(undefined,{maximumFractionDigits:2}) }}</span> credits left</p>
            <form @submit.prevent="send" class="flex gap-2"><label for="message" class="sr-only">Message</label><input id="message" v-model="newMessage" maxlength="4000" :disabled="sending || !live" class="field min-w-0 flex-1" :placeholder="live ? 'Message…' : pending ? 'Waiting for acceptance…' : 'Request to continue…'" /><button class="action !px-4" :disabled="sending || !newMessage.trim() || !live">Send</button></form>
            <p v-if="error" role="alert" class="mt-2 text-xs text-error">{{ error }}</p><p v-for="e in {...action.errors,...request.errors}" :key="e" role="alert" class="mt-2 text-xs text-error">{{ e }}</p>
        </footer>
        <Modal :show="showAgreement" :closeable="!request.processing" @close="showAgreement=false"><form @submit.prevent="submitRequest" class="space-y-5 p-6"><h2 class="font-serif text-2xl">Continue with {{ partner?.name }}</h2><p class="rounded-xl bg-accent-soft p-4 text-accent-text">{{ agreement?.rate }} credits / hour</p><p class="text-sm leading-6 text-muted">Charging begins only after counselor acceptance. Your previous messages stay in this conversation. A {{ agreement?.disconnect_seconds }}-second period without interaction or connection ends the reading; unconfirmed time is not charged.</p><label class="flex gap-3 text-sm"><input v-model="request.hide_notice" type="checkbox" /> Don’t show again for this counselor at this rate.</label><p v-for="e in request.errors" :key="e" class="text-error">{{ e }}</p><div class="flex flex-wrap gap-3"><button type="button" class="text-muted" :disabled="request.processing" @click="showAgreement=false">Cancel</button><button class="action" :disabled="request.processing">Agree & request to continue</button></div></form></Modal>
    </main>
</template>
