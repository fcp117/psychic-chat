<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ document: String });
const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth?.user));
const documents = {
    terms: { title: 'Terms of Service', sections: [
        ['Important draft', 'This draft must be reviewed and completed by the business owner and qualified legal adviser before publication. Replace the effective date, company details, support contact, governing-law language, and any business-specific terms.'],
        ['Using Intuition Island', 'Intuition Island is for adults who meet the platform’s eligibility requirements. You are responsible for keeping your account credentials private and for activity performed through your account. Do not use the platform to break the law, harass others, impersonate another person, or share content that infringes another person’s rights.'],
        ['Counselor conversations', 'Counselors are independent users of the platform. A request does not guarantee a counselor’s availability or acceptance. Read the displayed rate before requesting a chat. The platform does not promise any particular outcome from a conversation.'],
        ['Changes and contact', 'We may update these terms when the service changes. Material changes should be communicated before they take effect where required. Questions should be sent to [CLIENT SUPPORT EMAIL].'],
    ]},
    privacy: { title: 'Privacy Notice', sections: [
        ['Important draft', 'Replace [CLIENT LEGAL NAME], [CLIENT ADDRESS], [CLIENT SUPPORT EMAIL], and the effective date before launch. This notice must be reviewed for the jurisdictions in which the service operates.'],
        ['Information collected', 'The service may collect account details such as name, username, email address, birthdate for eligibility, profile information, uploaded profile photos, chat and billing records, technical logs, and support communications. Do not provide highly sensitive information unless the final policy expressly explains how it is handled.'],
        ['How information is used', 'Information is used to create and secure accounts, verify email addresses, provide chats and account features, prevent abuse, keep financial and operational records, respond to support requests, and improve the service. We use only the information necessary for these stated purposes.'],
        ['Sharing, retention, and rights', 'Information may be shared with service providers needed to operate the platform, such as hosting, email delivery, payment providers, and AI providers when that feature is enabled. The final policy must state retention periods, international transfers if any, contact details, and how a person can request access, correction, deletion, or object to processing.'],
        ['Automated features', 'The optional Isla guide provides general educational information from an approved library. It is not a live counselor and should not receive sensitive personal information. Its answers are subject to safety and usage limits.'],
    ]},
    refunds: { title: 'Refund Policy', sections: [
        ['Important draft', 'This is a business-policy draft, not a promise of refunds. The client must choose the final eligibility rules, support period, payment-provider process, and contact details before launch.'],
        ['Credits and chats', 'Credits are used for confirmed elapsed chat time after a counselor accepts a request. Before a chat starts, members should review the displayed rate. Billing and any refund decision should be recorded in the account’s transaction history.'],
        ['Requesting a review', 'If a member believes a charge was made in error, they should contact [CLIENT SUPPORT EMAIL] within [NUMBER] days and include the relevant transaction or chat reference. The business will review the available records and communicate its decision.'],
        ['Provider disputes', 'A payment-provider dispute or chargeback may affect account access while it is reviewed. The final policy must explain how provider fees, reversals, and duplicate payments are handled.'],
    ]},
    disclaimer: { title: 'Counselor & Service Disclaimer', sections: [
        ['Educational and entertainment context', 'Intuition Island provides a venue for conversations and general educational content. It does not provide medical, mental-health, legal, financial, emergency, or other regulated professional advice.'],
        ['No guarantees or predictions', 'Counselors and the Isla guide must not present predictions, readings, impressions, or general information as certain facts or guaranteed outcomes. Users remain responsible for their own decisions.'],
        ['Emergency support', 'Do not use the platform for emergencies, crisis support, self-harm concerns, or immediate danger. Contact local emergency services or a qualified crisis service instead.'],
        ['Independent counselors', 'Counselors are responsible for their own statements and conduct, subject to platform rules and applicable law. The client must finalize the counselor agreement, moderation process, and reporting procedure before launch.'],
    ]},
};
const content = computed(() => documents[props.document] || documents.terms);
</script>

<template>
    <Head :title="content.title" />
    <AuthenticatedLayout v-if="signedIn"><main class="mx-auto max-w-4xl px-5 py-14 sm:px-8"><p class="text-xs font-semibold uppercase tracking-[0.28em] text-accent-text">Draft for review</p><h1 class="mt-4 font-serif text-4xl sm:text-5xl">{{ content.title }}</h1><p class="mt-4 text-sm text-muted">Last updated: [EFFECTIVE DATE]</p><article class="mt-10 space-y-8 rounded-3xl border border-border bg-surface p-7 sm:p-10"><section v-for="([heading, body]) in content.sections" :key="heading"><h2 class="font-serif text-2xl">{{ heading }}</h2><p class="mt-3 leading-7 text-muted">{{ body }}</p></section></article></main><footer class="border-t border-border px-5 py-6 text-center text-xs text-muted"><Link :href="route('terms')" class="underline">Terms</Link><span class="px-2">·</span><Link :href="route('privacy')" class="underline">Privacy</Link><span class="px-2">·</span><Link :href="route('refunds')" class="underline">Refunds</Link><span class="px-2">·</span><Link :href="route('service.disclaimer')" class="underline">Disclaimer</Link></footer></AuthenticatedLayout>
    <div v-else class="min-h-screen bg-page text-content"><header class="border-b border-border bg-surface"><nav class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4 sm:px-8"><Link :href="route('home')" class="flex items-center gap-2"><ApplicationLogo class="h-8 w-8 text-primary" /><span class="font-serif text-lg">Intuition Island</span></Link><Link :href="route('about')" class="text-sm text-accent-text underline">About</Link></nav></header><main class="mx-auto max-w-4xl px-5 py-14 sm:px-8"><p class="text-xs font-semibold uppercase tracking-[0.28em] text-accent-text">Draft for review</p><h1 class="mt-4 font-serif text-4xl sm:text-5xl">{{ content.title }}</h1><p class="mt-4 text-sm text-muted">Last updated: [EFFECTIVE DATE]</p><article class="mt-10 space-y-8 rounded-3xl border border-border bg-surface p-7 sm:p-10"><section v-for="([heading, body]) in content.sections" :key="heading"><h2 class="font-serif text-2xl">{{ heading }}</h2><p class="mt-3 leading-7 text-muted">{{ body }}</p></section></article></main><footer class="border-t border-border px-5 py-6 text-center text-xs text-muted"><Link :href="route('terms')" class="underline">Terms</Link><span class="px-2">·</span><Link :href="route('privacy')" class="underline">Privacy</Link><span class="px-2">·</span><Link :href="route('refunds')" class="underline">Refunds</Link><span class="px-2">·</span><Link :href="route('service.disclaimer')" class="underline">Disclaimer</Link></footer></div>
</template>
