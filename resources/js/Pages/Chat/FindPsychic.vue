<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChatTabs from '@/Components/ChatTabs.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
const props = defineProps({ psychics: Object, filters: Object, billing: Object });
const page = usePage(); const canRequest = computed(() => page.props.auth.user.role === 'user');
const search = useForm({ q: props.filters.q || '' });
const notificationOnly = ref(false);
const selected = ref(null); const showAgreement = ref(false);
const reading = useForm({ accepted_rate: 0, consent: true, hide_notice: false });
const notifications = useForm({ show_rate_notice: true, consent: false, accepted_rate: 0 });
const find = () => search.get(route('psychics.index'), { preserveState: false, replace: true });
const requestReading = psychic => {
    notificationOnly.value = false; selected.value = psychic; reading.clearErrors(); reading.accepted_rate = psychic.rate_per_hour; reading.hide_notice = false;
    if (psychic.show_rate_notice) { reading.consent = true; showAgreement.value = true; }
    else { reading.consent = false; submit(); }
};
const submit = () => {
    if (notificationOnly.value) {
        notifications.show_rate_notice = !reading.hide_notice; notifications.consent = true; notifications.accepted_rate = reading.accepted_rate;
        notifications.patch(route('psychics.notifications', selected.value.id), { preserveScroll: true, onSuccess: () => { showAgreement.value = false; } });
    } else reading.post(route('psychics.chat', selected.value.id), { onSuccess: () => { showAgreement.value = false; } });
};
const toggleNotice = psychic => {
    notifications.clearErrors(); notificationOnly.value = true;
    if (psychic.show_rate_notice && !psychic.has_rate_agreement) { selected.value = psychic; reading.accepted_rate = psychic.rate_per_hour; reading.consent = true; reading.hide_notice = true; reading.clearErrors(); showAgreement.value = true; }
    else { notifications.show_rate_notice = !psychic.show_rate_notice; notifications.consent = false; notifications.accepted_rate = psychic.rate_per_hour; notifications.patch(route('psychics.notifications', psychic.id), { preserveScroll: true }); }
};
</script>
<template>
    <Head title="Find Psychic" /><AuthenticatedLayout>
        <section class="mx-auto max-w-5xl px-6 py-12">
            <p class="text-xs uppercase tracking-widest text-accent-text">Choose your connection</p><h1 class="mt-3 font-serif text-4xl">Find Psychic</h1><p class="mt-4 text-muted">Find someone by name. Review their hourly rate before requesting a reading.</p><ChatTabs />
            <form @submit.prevent="find" class="mt-7 flex flex-wrap gap-3"><label for="psychic-search" class="sr-only">Psychic’s name</label><input id="psychic-search" v-model="search.q" type="search" maxlength="100" placeholder="Search by name…" class="field min-w-0 flex-1" /><button class="action" :disabled="search.processing">Search</button><Link v-if="filters.q" :href="route('psychics.index')" class="self-center text-accent-text underline">Clear search</Link></form>
            <p v-for="e in search.errors" :key="e" class="mt-3 text-error">{{ e }}</p><p v-for="e in notifications.errors" :key="e" class="mt-3 text-error">{{ e }}</p>
            <p class="mt-5 text-sm text-muted">A listing does not guarantee the counselor is online. Charging starts only after acceptance. Minimum starting balance: {{ billing.minimum_credits }} credits.</p>
            <p v-if="!canRequest" class="mt-4 rounded-lg bg-accent-soft p-4 text-accent-text">Only User accounts can request paid readings. Counselors receive requests in Chat.</p>
            <p v-for="e in reading.errors" :key="e" role="alert" class="mt-3 text-error">{{ e }}</p>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="psychic in psychics.data" :key="psychic.id" class="rounded-2xl border border-border bg-surface p-6">
                    <div class="flex items-start justify-between"><div aria-hidden="true" class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-xl text-accent-text">{{ psychic.name.charAt(0).toUpperCase() }}</div>
                        <Dropdown v-if="canRequest" align="right" width="48"><template #trigger><button type="button" :aria-label="'Options for ' + psychic.name" class="rounded-lg px-3 py-1 text-2xl text-muted hover:bg-surface-hover">⋮</button></template><template #content><button type="button" :disabled="notifications.processing" @click="toggleNotice(psychic)" class="w-full px-4 py-3 text-left text-sm hover:bg-surface-hover">Rate notifications: {{ psychic.show_rate_notice ? 'On' : 'Off' }}<span class="mt-1 block text-xs text-muted">{{ psychic.show_rate_notice ? 'Turn off reminders' : 'Turn on reminders' }}</span></button></template></Dropdown>
                    </div>
                    <p class="text-xs uppercase tracking-widest text-accent-text">Psychic counselor</p><h2 class="mt-2 text-lg font-semibold">{{ psychic.name }}</h2><p class="mt-2 text-sm text-muted">{{ psychic.rate_per_hour }} credits / hour</p>
                    <button v-if="canRequest" type="button" :disabled="reading.processing" @click="requestReading(psychic)" class="action mt-6 w-full">Request chat</button>
                </article>
            </div>
            <div v-if="!psychics.data.length" class="mt-8 rounded-2xl border border-border bg-surface p-9"><h2 class="font-serif text-2xl">{{ filters.q ? 'No matching psychics.' : 'No other approved psychics yet.' }}</h2><p class="mt-3 text-muted">Try another search or check back later.</p></div><PageLinks :data="psychics" />
        </section>
        <Modal :show="showAgreement" :closeable="!reading.processing" @close="showAgreement = false"><form @submit.prevent="submit" class="space-y-5 p-7 text-content"><h2 class="font-serif text-2xl">Your reading with {{ selected?.name }}</h2><p class="rounded-xl bg-accent-soft p-5 text-xl text-accent-text">{{ selected?.rate_per_hour }} credits per hour</p><p class="text-sm leading-6 text-muted">Billing begins only when the counselor accepts. You pay for confirmed elapsed seconds, not a full hour. Either person may end the reading. It stops when your credits run out or either participant is inactive or disconnected for {{ billing.disconnect_seconds }} seconds; disconnected time is not charged. Interact with the chat to stay active during your reading.</p><label class="flex items-start gap-3 text-sm"><input v-model="reading.hide_notice" type="checkbox" class="mt-1" /><span>Don’t show again for this counselor at this rate. You can turn rate notifications back on from the ⋮ menu. A rate change always requires a new agreement.</span></label><p v-for="e in reading.errors" :key="e" role="alert" class="text-error">{{ e }}</p><p v-for="e in notifications.errors" :key="e" role="alert" class="text-error">{{ e }}</p><div class="flex gap-3"><button type="button" class="rounded-full border border-border px-5 py-3" :disabled="reading.processing" @click="showAgreement = false">Cancel</button><button class="action" :disabled="reading.processing || notifications.processing">{{ notificationOnly ? 'Agree & save notification preference' : 'Agree & request chat' }}</button></div></form></Modal>
    </AuthenticatedLayout>
</template>
