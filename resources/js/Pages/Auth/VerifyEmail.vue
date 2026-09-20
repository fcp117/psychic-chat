<script setup>
import { computed, ref } from 'vue';
import LoginLayout from '@/Layouts/LoginLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({ status: String });
const page = usePage();
const form = useForm({ code: '' });
const resend = useForm({});
const codeInput = ref(null);
const busy = computed(() => form.processing || resend.processing);
const error = computed(() => form.errors.code || resend.errors.code || page.props.errors?.code);

const updateCode = (event) => {
    const value = event.target.value.replace(/[^0-9]/g, '').slice(0, 6);
    form.code = value;
    event.target.value = value;
};
const verify = () => {
    if (busy.value) return;
    resend.clearErrors();
    form.post(route('verification.verify'), {
        onSuccess: () => form.reset(),
        onError: () => codeInput.value?.focus(),
    });
};
const sendAgain = () => {
    if (busy.value) return;
    form.clearErrors();
    resend.post(route('verification.send'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); codeInput.value?.focus(); },
    });
};
</script>

<template>
    <Head title="Verify your email" />
    <LoginLayout title="One last step" description="Verify your email to begin your journey with Psychic Chat.">
        <div class="mb-7 flex items-start gap-3 rounded-2xl bg-accent-soft p-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface text-accent-text" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="3" /><path d="m4 7 8 6 8-6" /></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs text-muted">Your verification email</p>
                <p class="mt-1 break-all text-sm font-semibold text-content">{{ $page.props.auth.user.email }}</p>
                <p class="mt-1 text-xs leading-5 text-muted">Check your inbox and spam folder for your code.</p>
            </div>
        </div>

        <p v-if="status === 'verification-code-sent' && !error" role="status" class="mb-5 flex items-start gap-2 rounded-xl border border-border px-4 py-3 text-sm text-success"><span aria-hidden="true">✓</span> A fresh code has been sent to your email.</p>

        <form @submit.prevent="verify" :aria-busy="form.processing">
            <InputLabel for="code" value="Six-digit verification code" />
            <input id="code" ref="codeInput" :value="form.code" @input="updateCode" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required :readonly="busy" :aria-invalid="Boolean(error)" aria-describedby="code-hint code-error" placeholder="000000" class="mt-3 block w-full min-w-0 rounded-xl border-border bg-page px-3 py-4 text-center font-mono text-3xl tracking-[0.25em] text-content shadow-sm placeholder:text-muted focus:border-primary focus:ring-primary sm:tracking-[0.4em]" />
            <p id="code-hint" class="mt-3 text-xs leading-5 text-muted">You can type or paste your code. It expires 10 minutes after it is sent.</p>
            <div id="code-error" aria-live="polite"><InputError class="mt-3" :message="error" /></div>
            <button type="submit" :disabled="busy || form.code.length !== 6" class="action mt-6 flex w-full items-center justify-center gap-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                <svg v-if="form.processing" class="h-4 w-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25" /><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" /></svg>
                {{ form.processing ? 'Verifying…' : 'Verify & continue' }}
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-muted">Didn’t receive an email?</p>
            <button type="button" :disabled="busy" @click="sendAgain" class="mt-1 rounded px-2 py-2 text-sm font-semibold text-accent-text underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:cursor-wait disabled:opacity-50">{{ resend.processing ? 'Sending your code…' : 'Send another code' }}</button>
            <p class="mt-1 text-xs leading-5 text-muted">Please wait 60 seconds between requests.<br />After five incorrect attempts, request a new code.</p>
        </div>

        <div class="mt-7 border-t border-border pt-5 text-center">
            <Link :href="route('logout')" method="post" as="button" :disabled="busy" class="rounded px-2 py-1 text-sm text-muted underline-offset-4 hover:text-content hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary disabled:opacity-50">Log out and return to login</Link>
        </div>
    </LoginLayout>
</template>
