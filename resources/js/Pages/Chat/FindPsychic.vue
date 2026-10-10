<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import CoachRating from '@/Components/CoachRating.vue';
import ConversationHeader from '@/Components/ConversationHeader.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ psychics: Object, filters: Object, billing: Object });
const page = usePage();
const canRequest = computed(() => page.props.auth.user.role === 'user');
const search = useForm({ q: props.filters.q || '' });
const activeAdvisor = ref(props.psychics.data?.[0] || null);
watch(() => props.psychics, value => {
    activeAdvisor.value = value.data.find(item => item.id === activeAdvisor.value?.id) || value.data[0] || null;
});
const notificationOnly = ref(false);
const selected = ref(null);
const showAgreement = ref(false);
const reading = useForm({ accepted_rate: 0, consent: true, hide_notice: false });
const notifications = useForm({ show_rate_notice: true, consent: false, accepted_rate: 0 });
const find = () => search.get(route('psychics.index'), { preserveState: false, replace: true });
const requestReading = advisor => {
    notificationOnly.value = false; selected.value = advisor; reading.clearErrors(); reading.accepted_rate = advisor.rate_per_hour; reading.hide_notice = false;
    if (advisor.show_rate_notice) { reading.consent = true; showAgreement.value = true; } else { reading.consent = false; submit(); }
};
const submit = () => {
    if (notificationOnly.value) {
        notifications.show_rate_notice = !reading.hide_notice; notifications.consent = true; notifications.accepted_rate = reading.accepted_rate;
        notifications.patch(route('psychics.notifications', selected.value.id), { preserveScroll: true, onSuccess: () => { showAgreement.value = false; } });
    } else reading.post(route('psychics.chat', selected.value.id), { onSuccess: () => { showAgreement.value = false; } });
};
const toggleNotice = advisor => {
    notifications.clearErrors(); notificationOnly.value = true;
    if (advisor.show_rate_notice && !advisor.has_rate_agreement) { selected.value = advisor; reading.accepted_rate = advisor.rate_per_hour; reading.consent = true; reading.hide_notice = true; reading.clearErrors(); showAgreement.value = true; }
    else { notifications.show_rate_notice = !advisor.show_rate_notice; notifications.consent = false; notifications.accepted_rate = advisor.rate_per_hour; notifications.patch(route('psychics.notifications', advisor.id), { preserveScroll: true }); }
};
</script>

<template>
    <Head title="Find a Spiritual Coach" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7">
            <section class="grid min-h-[calc(100svh-8.5rem)] overflow-x-auto rounded-3xl border border-border bg-surface shadow-xl shadow-primary/10 grid-cols-[minmax(15rem,28%)_minmax(24rem,1fr)]">
                <aside class="flex min-h-0 flex-col border-b border-border bg-page/60 border-b-0 border-r">
                    <ConversationHeader directory />
                    <div class="shrink-0 px-4 pb-2 pt-3">
                        <form @submit.prevent="find"><label for="psychic-search" class="sr-only">Spiritual Coach’s name</label><div class="flex gap-2"><input id="psychic-search" v-model="search.q" type="search" maxlength="100" placeholder="Search by name…" class="field min-w-0 flex-1 text-sm" /><button class="action !px-4 !py-2.5 text-sm" :disabled="search.processing">Search</button></div><Link v-if="filters.q" :href="route('psychics.index')" class="mt-2 inline-block text-xs text-accent-text hover:underline">Clear search</Link></form>
                    </div>
                    <div v-if="psychics.data.length" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-4 pt-2">
                        <button v-for="advisor in psychics.data" :key="advisor.id" type="button" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left transition hover:bg-accent-soft" :class="activeAdvisor?.id === advisor.id ? 'bg-accent-soft ring-1 ring-primary/20' : ''" @click="activeAdvisor = advisor">
                            <UserAvatar :user="advisor" class="h-11 w-11 shrink-0 text-sm" />
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-content">{{ advisor.name }}</span><CoachRating :rating="advisor.rating" class="mt-1.5" /><span class="mt-1 block text-xs text-muted">1 minute per reading minute</span></span>
                            <span class="text-lg text-muted" aria-hidden="true">›</span>
                        </button>
                        <PageLinks :data="psychics" />
                    </div>
                    <div v-else class="px-5 pb-6 pt-3"><p class="text-sm font-medium text-content">{{ filters.q ? 'No matching spiritual coaches' : 'No spiritual coaches available yet' }}</p><p class="mt-1 text-sm leading-6 text-muted">Try another name or check back later.</p></div>
                </aside>
                <section class="flex min-h-[32rem] flex-col bg-gradient-to-br from-page via-surface to-accent-soft/40">
                    <template v-if="activeAdvisor">
                        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface/80 p-4"><div class="flex min-w-0 items-center gap-3"><UserAvatar :user="activeAdvisor" class="h-12 w-12 shrink-0 text-base" /><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent-text">Spiritual Coach</p><h2 class="truncate font-serif text-2xl text-content">{{ activeAdvisor.name }}</h2><CoachRating :rating="activeAdvisor.rating" class="mt-1" /></div></div><Dropdown v-if="canRequest" align="right" width="48"><template #trigger><button type="button" :aria-label="'Options for ' + activeAdvisor.name" class="rounded-xl px-3 py-2 text-xl text-muted hover:bg-surface-hover">⋮</button></template><template #content><button type="button" :disabled="notifications.processing" class="w-full px-4 py-3 text-left text-sm hover:bg-surface-hover" @click="toggleNotice(activeAdvisor)">Rate notifications: {{ activeAdvisor.show_rate_notice ? 'On' : 'Off' }}<span class="mt-1 block text-xs text-muted">{{ activeAdvisor.show_rate_notice ? 'Turn off reminders' : 'Turn on reminders' }}</span></button></template></Dropdown></header>
                        <div class="flex flex-1 items-center justify-center p-6 sm:p-10"><div class="w-full max-w-xl rounded-3xl border border-white/80 bg-surface/80 p-6 shadow-lg shadow-primary/10 backdrop-blur sm:p-8"><p class="text-xs font-semibold uppercase tracking-[0.22em] text-accent-text">Available for a reading</p><h3 class="mt-3 font-serif text-4xl text-content">Connect with {{ activeAdvisor.name }}</h3><p class="mt-5 text-sm leading-7 text-muted">Review the rate before you request a chat. You will only be charged for confirmed elapsed time after this Spiritual Coach accepts your request.</p><div class="mt-7 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-accent-soft p-5"><div><p class="text-xs font-semibold uppercase tracking-widest text-accent-text">Reading rate</p><p class="mt-1 text-2xl font-semibold text-content">1 <span class="text-base font-normal">minute per reading minute</span></p></div><Link :href="route('counselor.profile', activeAdvisor.id)" class="text-sm font-semibold text-accent-text hover:underline">View profile →</Link></div><p v-if="canRequest" class="mt-5 text-xs leading-5 text-muted">Minimum starting balance: 1 minute. Availability is not guaranteed.</p><button v-if="canRequest" type="button" :disabled="reading.processing" class="action mt-7 w-full !py-3.5" @click="requestReading(activeAdvisor)">Request chat</button><Link v-if="canRequest" :href="route('bookings.index')" class="mt-3 block text-center text-sm font-semibold text-accent-text underline">Book a session</Link><Link v-if="canRequest" :href="route('conversation.open', activeAdvisor.id)" method="post" as="button" class="mt-3 w-full text-sm font-semibold text-accent-text underline">Send a free private message</Link><p v-if="!canRequest" class="mt-6 rounded-xl bg-accent-soft p-4 text-sm leading-6 text-accent-text">Spiritual Coaches receive reading requests in Chat.</p></div></div>
                    </template>
                    <div v-else class="m-auto max-w-md px-6 text-center"><span class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/10 text-3xl text-accent-text">✦</span><h2 class="mt-6 font-serif text-3xl text-content">Choose a Spiritual Coach</h2><p class="mt-4 text-sm leading-7 text-muted">Select a Spiritual Coach from the left to view their rate and request a chat.</p></div>
                </section>
            </section>
            <p v-for="error in search.errors" :key="error" class="mt-3 text-error">{{ error }}</p><p v-for="error in notifications.errors" :key="error" class="mt-3 text-error">{{ error }}</p><p v-for="error in reading.errors" :key="error" class="mt-3 text-error">{{ error }}</p>
        </main>
        <Modal :show="showAgreement" :closeable="!reading.processing" @close="showAgreement = false"><form class="space-y-5 p-7 text-content" @submit.prevent="submit"><h2 class="font-serif text-2xl">Your reading with {{ selected?.name }}</h2><p class="rounded-xl bg-accent-soft p-5 text-xl text-accent-text">1 minute per reading minute · all coaches</p><p class="text-sm leading-6 text-muted">Billing begins only when the Spiritual Coach accepts. Your session total rounds up to the next whole minute when ended (for example, 12:05 uses 13 minutes). Either person may end the reading. It stops when your minutes run out or either participant is disconnected for {{ billing.disconnect_seconds }} seconds; disconnected time is not charged. You do not need to move the mouse or type to keep a connected reading active.</p><label class="flex items-start gap-3 text-sm"><input v-model="reading.hide_notice" type="checkbox" class="mt-1" /><span>Don’t show again for this Spiritual Coach at this rate. You can turn rate notifications back on from the ⋮ menu. A rate change always requires a new agreement.</span></label><p v-for="error in reading.errors" :key="error" role="alert" class="text-error">{{ error }}</p><p v-for="error in notifications.errors" :key="error" role="alert" class="text-error">{{ error }}</p><div class="flex gap-3"><button type="button" class="rounded-full border border-border px-5 py-3" :disabled="reading.processing" @click="showAgreement = false">Cancel</button><button class="action" :disabled="reading.processing || notifications.processing">{{ notificationOnly ? 'Agree & save notification preference' : 'Agree & request chat' }}</button></div></form></Modal>
    </AuthenticatedLayout>
</template>
