<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConversationHeader from '@/Components/ConversationHeader.vue';
import Modal from '@/Components/Modal.vue';
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue';
import axios from 'axios';
const props = defineProps({ session: Object, sessions: Array, conversationId: Number, initialMessages: Array, currentUser: Object });
const session = ref(props.session), balance = ref(props.currentUser.available_credits);
const messages = ref([...props.initialMessages]), newMessage = ref(''), sending = ref(false);
const messageDate = message => {
    if (!message?.created_at) return null;
    const date = new Date(message.created_at);
    return Number.isNaN(date.getTime()) ? null : date;
};
const showTimestamp = index => {
    const current = messageDate(messages.value[index]);
    const previous = messageDate(messages.value[index - 1]);
    return current && (!previous || current.toDateString() !== previous.toDateString() || current - previous >= 300000);
};
const timestamp = message => messageDate(message)?.toLocaleString(undefined, {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
}) || '';
const online=ref(navigator.onLine),connectedOnce=ref(false),socketState=ref('connecting');
let lastAttempt=null;
const socketChange=({current})=>socketState.value=current;
const onlineChange=()=>{online.value=navigator.onLine;if(online.value)heartbeat();};
const error = ref(''), connectionError = ref(false), container = ref(null), action = useForm({});
const agreement = ref(null), showAgreement = ref(false), loadingAgreement = ref(false);
const request = useForm({accepted_rate: 0, consent: true, hide_notice: false});
const showEnd = ref(false);
const showReport = ref(false), reportSent = ref(false);
const report = useForm({ reason: '', details: '' });
const submitReport = () => report.post(route('chat.report', session.value.id), { preserveScroll: true, onSuccess: () => { showReport.value=false; report.reset(); reportSent.value=true; } });
const ending = useForm({ reason: '' });
const confirmEnd = () => {
    if (!ending.reason || ending.processing) return;
    ending.post(route('chat.end', session.value.id), { preserveScroll: true, onSuccess: () => { showEnd.value = false; ending.reset(); heartbeat(); } });
};
const isCounselor = computed(() => props.currentUser.id === session.value.counselor_id);
const partner = computed(() => isCounselor.value ? session.value.client : session.value.counselor);
const sessionPartner = item => item.client_id === props.currentUser.id ? item.counselor : item.client;
const initials = item => (sessionPartner(item)?.name || 'I').split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase();
const statusLabel = status => status === 'active' ? 'Active now' : status === 'pending' ? 'Request pending' : 'Conversation ended';
const live = computed(() => session.value.status === 'active');
const pending = computed(() => session.value.status === 'pending');
const now = ref(Date.now()); let serverOffset = 0;
const present = value => value && now.value - Date.parse(value) < (session.value.disconnect_seconds || 30) * 1000;
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
        });
        if (!stopped) { session.value = result.data.session; balance.value = result.data.balance; serverOffset = Date.parse(result.data.server_time)-Date.now(); connectionError.value = false; connectedOnce.value=true; result.data.messages.forEach(append); }
    } catch { if (!stopped) connectionError.value = true; }
    finally { polling = false; }
};
onMounted(() => {
    window.addEventListener('online',onlineChange);window.addEventListener('offline',onlineChange);
    const connection=window.Echo?.connector?.pusher?.connection;socketState.value=connection?.state||'unavailable';connection?.bind('state_change',socketChange);
    scroll(); heartbeat(); timer = setInterval(heartbeat, 5000); clock = setInterval(() => { now.value = Date.now()+serverOffset; }, 1000);
    ['pointerdown','pointermove','keydown','touchstart','scroll'].forEach(event => window.addEventListener(event, markActivity, {passive:true,capture:true}));
    document.addEventListener('visibilitychange',visible);
    window.Echo?.private(`chat.${props.conversationId}`).listen('.MessageSent', event => append(event.message)).listen('.ReadingUpdated', heartbeat);
});
onUnmounted(() => {
    window.removeEventListener('online',onlineChange);window.removeEventListener('offline',onlineChange);window.Echo?.connector?.pusher?.connection?.unbind('state_change',socketChange);
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
    if (sending.value || !newMessage.value.trim() || (!live.value && !lastAttempt)) return;
    const content=newMessage.value;
    if(!lastAttempt || lastAttempt.content!==content) lastAttempt={content,key:crypto.randomUUID(),sessionId:session.value.id};
    const attempt=lastAttempt; sending.value=true; error.value=''; markActivity();
    try { const result=await axios.post(route('chat.message',attempt.sessionId),{content,request_key:attempt.key},{headers:{'X-Socket-ID':window.Echo?.socketId() ?? ''}}); append(result.data.message); if(newMessage.value===content)newMessage.value='';lastAttempt=null; }
    catch(e) { error.value=e.response?.data?.errors?.reading?.[0] || e.response?.data?.message || 'We could not confirm delivery. Your message is still here; retrying will not send it twice.'; heartbeat(); }
    finally { sending.value=false; }
};
</script>
<template>
    <Head :title="partner?.name || 'Conversation'" />
    <AuthenticatedLayout>
    <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7">
        <div class="grid min-h-[calc(100svh-8.5rem)] overflow-x-auto rounded-3xl border border-border bg-surface shadow-xl shadow-primary/10 grid-cols-[minmax(15rem,28%)_minmax(24rem,1fr)]">
            <aside class="flex min-h-0 flex-col border-b border-border bg-page/60 border-b-0 border-r">
                <ConversationHeader :balance="Number(balance)" />
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-4 pt-2"><Link v-for="item in sessions" :key="item.id" :href="route('chat.room', item.conversation_id)" class="group flex items-center gap-3 rounded-2xl px-3 py-3 transition hover:bg-accent-soft focus-visible:bg-accent-soft" :class="item.conversation_id === conversationId ? 'bg-accent-soft ring-1 ring-inset ring-primary/20' : ''"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-accent-text">{{ initials(item) }}</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-content">{{ sessionPartner(item)?.name || 'Your reading' }}</span><span class="mt-1 block truncate text-xs" :class="item.status === 'active' ? 'text-success' : 'text-muted'">{{ statusLabel(item.status) }}</span></span><span class="text-lg text-muted transition group-hover:translate-x-0.5 group-hover:text-accent-text" aria-hidden="true">›</span></Link></div>
            </aside>
            <section class="flex min-h-[32rem] min-w-0 flex-col bg-gradient-to-br from-page via-surface to-accent-soft/40 p-3 sm:p-5">
        <header class="shrink-0 border border-border bg-surface p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <img v-if="partner?.profile_photo_url" :src="partner.profile_photo_url" :alt="partner.name" class="h-12 w-12 shrink-0 rounded-full object-cover" />
                    <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-accent-soft font-semibold text-accent-text">{{ initials(session) }}</span>
                    <div class="min-w-0"><h1 class="break-words font-serif text-xl sm:text-2xl">{{ partner?.name || 'Your conversation' }}</h1><p class="mt-1 text-xs text-muted"><span :class="present(partnerSeen) ? 'text-success' : 'text-muted'">●</span> {{ present(partnerSeen) ? 'Online' : 'Offline' }}</p></div>
                </div>
                <button v-if="live" class="action ml-auto !px-4 !py-2" :disabled="ending.processing" @click="ending.reset(); ending.clearErrors(); showEnd = true">End reading</button>
                <span v-else class="ml-auto rounded-full bg-accent-soft px-3 py-1 text-xs text-accent-text">{{ pending ? 'Request pending' : 'Reading ended' }}</span>
                <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center border border-border text-muted hover:bg-accent-soft" aria-label="Report this participant" title="Report this participant" @click="report.clearErrors(); showReport=true"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 21V3m0 1c5-4 9 4 14 0v10c-5 4-9-4-14 0" /></svg></button>
            </div>
            <p v-if="live" class="mt-3 text-xs text-muted">{{ session.agreed_rate }} credits/hour · No inactivity cutoff while connected. Use End reading when finished.</p>
            <div v-if="pending" class="mt-3 flex flex-wrap items-center gap-3"><button v-if="isCounselor" class="action !px-4 !py-2" :disabled="action.processing" @click="action.post(route('chat.accept',session.id))">Accept & continue</button><p v-else class="text-xs text-muted" role="status">Request sent. Waiting for acceptance; no charge yet.</p><button class="text-xs text-muted underline" :disabled="action.processing" @click="action.post(route('chat.end',session.id))">Cancel request</button></div>
        </header>
        <div v-if="!online || connectionError" class="flex flex-wrap items-center justify-between gap-2 px-1 py-2 text-xs" role="status"><span class="text-error">{{ !online ? 'Offline · waiting for your connection' : 'Reconnecting to the server…' }}</span><button v-if="connectionError && online" class="text-accent-text underline" @click="heartbeat">Reconnect now</button></div>
        <div ref="container" role="log" aria-label="Conversation messages" aria-live="polite" class="-mt-px flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain border border-border bg-page p-3 sm:p-5">
            <p v-if="reportSent" role="status" class="text-center text-xs text-muted">Report submitted for administrator review.</p>
            <p v-if="!messages.length" class="py-8 text-center text-sm text-muted">Your messages stay here across readings.</p>
            <template v-for="(msg,index) in messages" :key="msg.id">
                <div v-if="showTimestamp(index)" class="flex w-full items-center justify-center gap-3 py-3"><span class="h-px w-8 shrink-0 bg-border" aria-hidden="true"></span><time :datetime="msg.created_at" class="text-center text-[11px] font-medium text-muted">{{ timestamp(msg) }}</time><span class="h-px w-8 shrink-0 bg-border" aria-hidden="true"></span></div>
                <div v-if="msg.kind === 'system'" :title="timestamp(msg)" class="mx-auto max-w-md rounded-xl bg-accent-soft px-4 py-3 text-center text-xs leading-5 text-accent-text"><span class="block font-medium">Intuition Island</span>{{ msg.content }}</div>
                <div v-else :title="timestamp(msg)" class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm" :class="msg.sender_id === currentUser.id ? 'self-end bg-primary text-on-primary' : 'self-start bg-surface text-content'"><p class="mb-1 text-xs opacity-75">{{ msg.sender?.name }}</p>{{ msg.content }}</div>
            </template>
            <div v-if="!live && !pending" class="mx-auto max-w-md rounded-xl border border-border bg-surface p-4 text-center text-sm"><p class="text-muted">This reading is inactive. Your conversation stays here.</p><button v-if="!isCounselor" class="mt-2 font-semibold text-accent-text underline" :disabled="loadingAgreement || request.processing" @click="continueChat">Click here to request to continue</button><p v-else class="mt-2 text-xs text-muted">You’ll receive a notification when the user requests to continue.</p></div>
        </div>
        <footer class="shrink-0 pt-3 pb-[env(safe-area-inset-bottom)]">
            <form @submit.prevent="send" class="flex items-center gap-3 rounded-2xl border border-border bg-surface p-2 shadow-sm"><label for="message" class="sr-only">Message</label><input id="message" v-model="newMessage" maxlength="4000" :disabled="sending || !live" class="field h-11 min-w-0 flex-1 !rounded-xl !border-0 !bg-transparent !shadow-none disabled:opacity-60" :placeholder="live ? 'Write a message…' : pending ? 'Waiting for acceptance…' : 'Request a reading to continue…'" /><button type="submit" class="action h-11 shrink-0 !rounded-xl !px-5 !py-0" :disabled="sending || !newMessage.trim() || !live">{{ sending ? 'Sending…' : 'Send' }}</button></form>
            <p v-if="error" role="alert" class="mt-2 text-xs text-error">{{ error }} <button v-if="newMessage.trim()" type="button" class="ml-2 font-semibold underline" :disabled="sending || !online" @click="send">{{ sending ? 'Retrying…' : 'Retry message' }}</button></p><p v-for="e in {...action.errors,...request.errors}" :key="e" role="alert" class="mt-2 text-xs text-error">{{ e }}</p>
        </footer>
        <Modal :show="showReport" max-width="md" :closeable="!report.processing" @close="showReport=false"><form class="space-y-4 p-6" @submit.prevent="submitReport"><h2 class="font-serif text-2xl">Report {{ partner?.name }}</h2><p class="text-sm text-muted">Reports are reviewed by administrators, not posted in this conversation. Reporting does not automatically end the reading.</p><label class="block">Reason<select v-model="report.reason" required class="field mt-2 w-full"><option disabled value="">Choose a reason</option><option value="harassment">Harassment or rude behavior</option><option value="inappropriate">Inappropriate content</option><option value="scam">Scam or payment abuse</option><option value="safety">Safety concern</option><option value="other">Other</option></select></label><label class="block">What happened?<textarea v-model="report.details" required minlength="10" maxlength="2000" rows="4" class="field mt-2 w-full" /></label><p v-for="error in report.errors" :key="error" class="text-error">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" :disabled="report.processing" @click="showReport=false">Cancel</button><button class="action" :disabled="report.processing">Submit report</button></div></form></Modal>
        <Modal :show="showEnd" max-width="md" :closeable="!ending.processing" @close="showEnd = false">
            <form class="space-y-5 p-6 text-content" @submit.prevent="confirmEnd">
                <h2 class="font-serif text-2xl">End this reading?</h2>
                <p class="text-sm leading-6 text-muted">Billing continues until you confirm. Your messages will stay here, and you can request another reading later.</p>
                <label class="block text-sm font-semibold">Reason for ending
                    <select v-model="ending.reason" required class="field mt-2 w-full"><option disabled value="">Choose a reason</option><option value="finished">Reading completed</option><option value="break">Taking a break</option><option value="time">Need to leave</option><option value="technical">Connection or technical issue</option><option value="not_fit">Not the right fit</option><option value="other">Other</option></select>
                </label>
                <p class="text-xs text-muted">The selected reason will be shared in this conversation.</p>
                <p v-for="message in ending.errors" :key="message" role="alert" class="text-sm text-error">{{ message }}</p>
                <div class="flex flex-wrap justify-end gap-3"><button type="button" class="rounded-full border border-border px-4 py-2 text-sm" :disabled="ending.processing" @click="showEnd = false">Keep chatting</button><button class="action !px-4 !py-2" :disabled="!ending.reason || ending.processing || !live">{{ ending.processing ? 'Ending…' : 'End reading' }}</button></div>
                <p v-if="!live" class="text-sm text-muted">This reading has already ended.</p>
            </form>
        </Modal>
        <Modal :show="showAgreement" :closeable="!request.processing" @close="showAgreement=false"><form @submit.prevent="submitRequest" class="space-y-5 p-6"><h2 class="font-serif text-2xl">Continue with {{ partner?.name }}</h2><p class="rounded-xl bg-accent-soft p-4 text-accent-text">{{ agreement?.rate }} credits / hour</p><p class="text-sm leading-6 text-muted">Charging begins only after spiritual coach acceptance. Your previous messages stay in this conversation. Inactivity does not end a connected reading. Losing connection for {{ agreement?.disconnect_seconds }} seconds ends the reading; unconfirmed time is not charged.</p><label class="flex gap-3 text-sm"><input v-model="request.hide_notice" type="checkbox" /> Don’t show again for this spiritual coach at this rate.</label><p v-for="e in request.errors" :key="e" class="text-error">{{ e }}</p><div class="flex flex-wrap gap-3"><button type="button" class="text-muted" :disabled="request.processing" @click="showAgreement=false">Cancel</button><button class="action" :disabled="request.processing">Agree & request to continue</button></div></form></Modal>
            </section>
        </div>
    </main>
    </AuthenticatedLayout>
</template>
