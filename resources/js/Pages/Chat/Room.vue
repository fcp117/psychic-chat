<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConversationHeader from '@/Components/ConversationHeader.vue';
import ConversationTools from '@/Components/ConversationTools.vue';
import CoachRating from '@/Components/CoachRating.vue';
import Modal from '@/Components/Modal.vue';
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue';
import axios from 'axios';
import { requestId } from '@/lib/request-id.mjs';
const props = defineProps({ session: Object, sessions: Array, conversationId: Number, initialMessages: Array, hasOlderMessages: Boolean, currentUser: Object });
const hasOlder = ref(props.hasOlderMessages), loadingOlder = ref(false), historyError = ref('');
const loadOlder = async () => {
    if (loadingOlder.value || !hasOlder.value) return;
    loadingOlder.value = true; historyError.value = '';
    try {
        const { data } = await axios.get(route('chat.history', props.conversationId), { params: { before: messages.value[0]?.id } });
        const oldHeight = container.value?.scrollHeight || 0;
        const oldTop = container.value?.scrollTop || 0;
        const known = new Set(messages.value.map(m => m.id));
        messages.value = [...data.messages.filter(m => !known.has(m.id)), ...messages.value];
        hasOlder.value = data.has_more;
        await nextTick();
        if (container.value) container.value.scrollTop = oldTop + container.value.scrollHeight - oldHeight;
    } catch { historyError.value = 'Could not load earlier messages. Please retry.'; }
    finally { loadingOlder.value = false; }
};
const session = ref(props.session), balance = ref(props.currentUser.spendable_minutes ?? props.currentUser.available_credits);
const attachment = ref(null), attachmentInput = ref(null);
const chooseAttachment = event => {
    const file = event.target.files[0];
    error.value = '';
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { error.value = 'Choose a file no larger than 5 MB.'; event.target.value = ''; return; }
    attachment.value = file;
};
const removeAttachment = () => { attachment.value = null; if (attachmentInput.value) attachmentInput.value.value = ''; };
const isImage = message => ['image/jpeg','image/png','image/webp'].includes(message.attachment_mime);
const messages = ref([...props.initialMessages]), newMessage = ref(''), sending = ref(false);
const messageDate = message => {
    if (!message?.created_at) return null;
    const date = new Date(message.created_at);
    return Number.isNaN(date.getTime()) ? null : date;
};
const showTimestamp = index => {
    const current = messageDate(messages.value[index]);
    const previous = messageDate(messages.value[index - 1]);
    return current && (!previous || current.toDateString() !== previous.toDateString());
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
const statusLabel = status => status === 'active' ? 'Active now' : status === 'pending' ? 'Request pending' : 'Free messaging';
const live = computed(() => session.value.status === 'active');
const pending = computed(() => session.value.status === 'pending');
const now = ref(Date.now()); let serverOffset = 0;
const tickDelta = computed(() => Math.max(0, Math.floor((now.value-Date.parse(session.value.timing?.sampled_at||''))/1000)) || 0);
const elapsedSeconds = computed(() => (session.value.timing?.elapsed||0) + (live.value && online.value && !connectionError.value ? Math.min(tickDelta.value,5) : 0));
const remainingSeconds = computed(() => Math.max(0,(session.value.timing?.remaining||0) - (live.value && online.value && !connectionError.value ? Math.min(tickDelta.value,5) : 0)));
const clockText = seconds => `${Math.floor(seconds/3600).toString().padStart(2,'0')}:${Math.floor(seconds%3600/60).toString().padStart(2,'0')}:${Math.floor(seconds%60).toString().padStart(2,'0')}`;
const present = value => value && now.value - Date.parse(value) < (session.value.disconnect_seconds || 30) * 1000;
const partnerSeen = computed(() => isCounselor.value ? session.value.client_seen_at : session.value.counselor_seen_at);
let timer, clock, polling = false, stopped = false, lastActivity = Date.now();
const markActivity = () => { if (!document.hidden) lastActivity = Date.now(); };
const visible = () => { if (!document.hidden) { markActivity(); heartbeat(); } };
const scroll = async () => { await nextTick(); if (container.value) container.value.scrollTop = container.value.scrollHeight; };
const append = message => {
    if (messages.value.some(m => m.id === message.id)) return;
    const atBottom = !container.value || container.value.scrollHeight - container.value.scrollTop - container.value.clientHeight < 100;
    messages.value.push(message); messages.value.sort((a,b)=>a.id-b.id);
    if (atBottom || message.sender_id === props.currentUser.id) scroll();
};
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
    if (sending.value || (!newMessage.value.trim() && !attachment.value)) return;
    const content=newMessage.value;
    if(!lastAttempt || lastAttempt.content!==content || lastAttempt.file!==attachment.value) lastAttempt={content,file:attachment.value,key:requestId(),sessionId:session.value.id};
    const attempt=lastAttempt; sending.value=true; error.value=''; markActivity();
    const payload = new FormData(); payload.append('content', content); payload.append('request_key', attempt.key); if (attempt.file) payload.append('attachment', attempt.file);
    try { const result=await axios.post(route('chat.message',attempt.sessionId),payload,{headers:{'X-Socket-ID':window.Echo?.socketId() ?? ''}}); append(result.data.message); if(newMessage.value===content)newMessage.value='';removeAttachment();lastAttempt=null; }
    catch(e) { error.value=Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'We could not confirm delivery. Your message is still here; retrying will not send it twice.'; heartbeat(); }
    finally { sending.value=false; }
};
</script>
<template>
    <Head :title="partner?.name || 'Conversation'" />
    <AuthenticatedLayout>
    <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7">
        <div class="conversation-layout conversation-room grid min-h-[calc(100svh-8.5rem)] overflow-x-auto rounded-3xl border border-border bg-surface shadow-xl shadow-primary/10 grid-cols-[minmax(15rem,28%)_minmax(24rem,1fr)]">
            <aside class="conversation-sidebar flex min-h-0 flex-col border-b border-border bg-page/60 border-b-0 border-r">
                <ConversationHeader :balance="Number(balance)" />
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-4 pt-2"><Link v-for="item in sessions" :key="item.id" :href="route('chat.room', item.conversation_id)" class="group flex items-center gap-3 rounded-2xl px-3 py-3 transition hover:bg-accent-soft focus-visible:bg-accent-soft" :class="item.conversation_id === conversationId ? 'bg-accent-soft ring-1 ring-inset ring-primary/20' : ''"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-accent-text">{{ initials(item) }}</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-content">{{ sessionPartner(item)?.name || 'Your reading' }}</span><span class="mt-1 block truncate text-xs" :class="item.status === 'active' ? 'text-success' : 'text-muted'">{{ statusLabel(item.status) }}</span></span><span class="text-lg text-muted transition group-hover:translate-x-0.5 group-hover:text-accent-text" aria-hidden="true">›</span></Link></div>
            </aside>
            <section class="flex min-h-[32rem] min-w-0 flex-col bg-gradient-to-br from-page via-surface to-accent-soft/40 p-3 sm:p-5">
        <div class="mb-2 flex items-center justify-between gap-2 md:hidden"><Link :href="route('chat.index')" class="inline-flex min-h-11 items-center text-sm font-semibold text-accent-text">← All conversations</Link><Link v-if="!isCounselor" :href="route('credits')" class="text-xs text-accent-text">{{ Number(balance).toFixed(2) }} min left</Link></div>
        <header class="shrink-0 border border-border bg-surface p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <img v-if="partner?.profile_photo_url" :src="partner.profile_photo_url" :alt="partner.name" class="h-12 w-12 shrink-0 rounded-full object-cover" />
                    <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-accent-soft font-semibold text-accent-text">{{ initials(session) }}</span>
                    <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent-text">{{ isCounselor ? 'Client' : 'Spiritual Coach' }}</p><h1 class="break-words font-serif text-2xl">{{ partner?.name || 'Your conversation' }}</h1><div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1"><CoachRating v-if="!isCounselor" :rating="partner?.rating" /><p class="text-xs text-muted"><span :class="present(partnerSeen) ? 'text-success' : 'text-muted'">●</span> {{ present(partnerSeen) ? 'Online' : 'Offline' }}</p></div></div>
                </div>
                <button v-if="live" class="ml-auto rounded-full bg-danger px-4 py-2 text-sm font-semibold text-on-danger hover:bg-danger-hover" :disabled="ending.processing" @click="ending.reset(); ending.clearErrors(); showEnd = true">End reading</button>
                <span v-else class="ml-auto rounded-full bg-accent-soft px-3 py-1 text-xs text-accent-text">{{ pending ? 'Request pending' : session.started_at ? 'Reading ended' : 'Free messaging' }}</span>
                <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center border border-border text-muted hover:bg-accent-soft" aria-label="Report this participant" title="Report this participant" @click="report.clearErrors(); showReport=true"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 21V3m0 1c5-4 9 4 14 0v10c-5 4-9-4-14 0" /></svg></button>
            </div>
            <div v-if="live" class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted"><span>Elapsed <strong class="tabular-nums text-content">{{ clockText(elapsedSeconds) }}</strong></span><span>Estimated remaining <strong class="tabular-nums text-content">{{ clockText(remainingSeconds) }}</strong></span><span>{{ session.billing_version === 2 ? '1 minute per minute · rounded up at end' : session.agreed_rate + ' legacy credits/hour' }}</span></div><p v-else class="mt-2 text-xs text-muted">Free private messaging · no billing outside an active reading.</p><p v-if="live && remainingSeconds <= 90" role="status" class="mt-2 text-xs font-semibold text-error">About {{ Math.ceil(remainingSeconds) }} seconds of reading time remain.<Link v-if="!isCounselor" :href="route('credits')" target="_blank" class="ml-2 underline">Add minutes (new tab)</Link></p>
            <div v-if="pending" class="mt-3 flex flex-wrap items-center gap-3"><button v-if="isCounselor && session.coach_available !== false" class="action !px-4 !py-2" :disabled="action.processing" @click="action.post(route('chat.accept',session.id))">Accept & continue</button><p v-else-if="session.coach_available === false" class="text-xs text-muted" role="status">This coach is not accepting new readings while Rainbow-only mode is enabled. You can cancel this request.</p><p v-else class="text-xs text-muted" role="status">Request sent. Waiting for acceptance; no charge yet.</p><button class="text-xs text-muted underline" :disabled="action.processing" @click="action.post(route('chat.end',session.id))">Cancel request</button></div>
        </header>
        <div class="shrink-0 py-2 text-xs"><Link v-if="!isCounselor && session.coach_available !== false" :href="route('bookings.index',{coach_id:partner?.id})" class="font-semibold text-accent-text underline">Book a session with {{partner?.name}}</Link><Link v-else-if="isCounselor" :href="route('bookings.index')" class="font-semibold text-accent-text underline">Bookings & availability</Link></div>
        <ConversationTools :session="session" :coach="isCounselor" :conversation-id="conversationId" @refresh="heartbeat" @message="append" />
        <div v-if="!online || connectionError" class="flex flex-wrap items-center justify-between gap-2 px-1 py-2 text-xs" role="status"><span class="text-error">{{ !online ? 'Offline · waiting for your connection' : 'Reconnecting to the server…' }}</span><button v-if="connectionError && online" class="text-accent-text underline" @click="heartbeat">Reconnect now</button></div>
        <div ref="container" role="log" aria-label="Conversation messages" aria-live="polite" class="-mt-px flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain border border-border bg-page p-3 sm:p-5">
            <button v-if="hasOlder" type="button" class="self-center text-xs text-accent-text underline" :disabled="loadingOlder" @click="loadOlder">{{ loadingOlder ? 'Loading…' : 'Load earlier messages' }}</button>
            <p v-if="historyError" role="alert" class="text-center text-xs text-error">{{ historyError }}</p>
            <p v-if="reportSent" role="status" class="text-center text-xs text-muted">Report submitted for administrator review.</p>
            <p v-if="!messages.length" class="py-8 text-center text-sm text-muted">Your messages stay here across readings.</p>
            <template v-for="(msg,index) in messages" :key="msg.id">
                <div v-if="showTimestamp(index)" class="flex w-full items-center justify-center gap-3 py-3"><span class="h-px w-8 shrink-0 bg-border" aria-hidden="true"></span><time :datetime="msg.created_at" class="text-center text-[11px] font-medium text-muted">{{ messageDate(msg)?.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'}) }}</time><span class="h-px w-8 shrink-0 bg-border" aria-hidden="true"></span></div>
                <div v-if="msg.kind === 'system'" :title="timestamp(msg)" class="mx-auto max-w-md rounded-xl bg-accent-soft px-4 py-3 text-center text-xs leading-5 text-accent-text"><span class="block font-medium">Intuition Island</span>{{ msg.content }}<time :datetime="msg.created_at" class="mt-1 block text-[10px] opacity-70">{{ messageDate(msg)?.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'}) }}</time></div>
                <div v-else :title="timestamp(msg)" class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm" :class="msg.sender_id === currentUser.id ? 'self-end bg-primary text-on-primary' : 'self-start bg-surface text-content'"><p class="mb-1 text-xs opacity-75">{{ msg.sender?.name }}</p>{{ msg.content }}<img v-if="msg.attachment_name && isImage(msg)" :src="route('conversation.attachment',msg.id)" :alt="msg.attachment_name" class="mt-2 max-h-80 max-w-full rounded-lg object-contain" loading="lazy" /><a v-else-if="msg.attachment_name" :href="route('conversation.attachment',msg.id)" class="mt-1 block text-xs underline">Download {{ msg.attachment_name }}</a><time :datetime="msg.created_at" class="mt-1 block text-right text-[10px] opacity-70">{{ messageDate(msg)?.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'}) }}</time></div>
            </template>
            <div v-if="!live && !pending" class="mx-auto max-w-md rounded-xl border border-border bg-surface p-4 text-center text-sm"><p class="text-muted">You can exchange free messages here. Start a paid reading only when you are both ready.</p><button v-if="!isCounselor && session.coach_available !== false" class="mt-2 font-semibold text-accent-text underline" :disabled="loadingAgreement || request.processing" @click="continueChat">Click here to request to continue</button><p v-else-if="session.coach_available === false" class="mt-2 text-xs text-muted">New readings are currently available with Coach Rainbow only. Your chat history stays here.</p><p v-else class="mt-2 text-xs text-muted">You’ll receive a notification when the user requests to continue.</p></div>
        </div>
        <footer class="shrink-0 pt-3 pb-[env(safe-area-inset-bottom)]">
            <Link v-if="!isCounselor && session.started_at && !live && !pending" :href="route('reviews.index')" class="mb-3 block rounded-xl border border-border bg-accent-soft px-4 py-3 text-sm text-accent-text">★ How was your reading? Leave feedback</Link>
            <div v-if="attachment" class="mb-2 flex min-w-0 items-center gap-2 text-xs text-muted"><span class="truncate">{{ attachment.name }}</span><button type="button" :disabled="sending" class="shrink-0 underline" @click="removeAttachment">Remove</button></div>
            <form @submit.prevent="send" class="flex items-center gap-3 rounded-2xl border border-border bg-surface p-2 shadow-sm"><label for="message" class="sr-only">Message</label><input id="message" v-model="newMessage" maxlength="4000" :disabled="sending" class="field h-11 min-w-0 flex-1 !rounded-xl !border-0 !bg-transparent !shadow-none disabled:opacity-60" :placeholder="live ? 'Write a message…' : 'Leave a free private message…'" /><input ref="attachmentInput" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf" :disabled="sending" @change="chooseAttachment" /><button type="button" aria-label="Attach image or PDF" title="Attach image or PDF (up to 5 MB)" :disabled="sending" class="flex h-11 w-9 shrink-0 items-center justify-center rounded-lg text-muted opacity-60 hover:bg-accent-soft hover:opacity-100 focus-visible:opacity-100" @click="attachmentInput?.click()"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 6-6a3 3 0 0 1 4 4l-8 8a5 5 0 0 1-7-7l9-9a7 7 0 0 1 10 10l-9 9"/></svg></button><button type="submit" class="action h-11 shrink-0 !rounded-xl !px-5 !py-0" :disabled="sending || (!newMessage.trim() && !attachment)">{{ sending ? 'Sending…' : 'Send' }}</button></form>
            <p v-if="error" role="alert" class="mt-2 text-xs text-error">{{ error }} <button v-if="newMessage.trim() || attachment" type="button" class="ml-2 font-semibold underline" :disabled="sending || !online" @click="send">{{ sending ? 'Retrying…' : 'Retry message' }}</button></p><p v-for="e in {...action.errors,...request.errors}" :key="e" role="alert" class="mt-2 text-xs text-error">{{ e }}</p>
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
        <Modal :show="showAgreement" :closeable="!request.processing" @close="showAgreement=false"><form @submit.prevent="submitRequest" class="space-y-5 p-6"><h2 class="font-serif text-2xl">Continue with {{ partner?.name }}</h2><p class="rounded-xl bg-accent-soft p-4 text-accent-text">1 minute per reading minute; session rounds up to whole minutes at end.</p><p class="text-sm leading-6 text-muted">Charging begins only after spiritual coach acceptance. Your previous messages stay in this conversation. Inactivity does not end a connected reading. Losing connection for {{ agreement?.disconnect_seconds }} seconds ends the reading; unconfirmed time is not charged.</p><label class="flex gap-3 text-sm"><input v-model="request.hide_notice" type="checkbox" /> Don’t show again for this spiritual coach at this rate.</label><p v-for="e in request.errors" :key="e" class="text-error">{{ e }}</p><div class="flex flex-wrap gap-3"><button type="button" class="text-muted" :disabled="request.processing" @click="showAgreement=false">Cancel</button><button class="action" :disabled="request.processing">Agree & request to continue</button></div></form></Modal>
            </section>
        </div>
    </main>
    </AuthenticatedLayout>
</template>
