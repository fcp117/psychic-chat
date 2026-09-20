<script setup>
import PasswordRequirements from '@/Components/PasswordRequirements.vue';
import { ref } from 'vue';
import LoginLayout from '@/Layouts/LoginLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const showPasswords = ref(false);
const form = useForm({ name: '', username: '', email: '', password: '', password_confirmation: '' });
const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Create an account" />
    <LoginLayout title="Your journey starts here" description="Create your account and make space for a new perspective." wide>
        <form class="space-y-5" @submit.prevent="submit" :aria-busy="form.processing">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="min-w-0">
                    <InputLabel for="name" value="Full name" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-2 block w-full" required maxlength="255" autocomplete="name" placeholder="Your name" :aria-invalid="Boolean(form.errors.name)" aria-describedby="name-error" />
                    <InputError id="name-error" class="mt-2" :message="form.errors.name" />
                </div>
                <div class="min-w-0">
                    <InputLabel for="username" value="Username" />
                    <TextInput id="username" v-model="form.username" type="text" class="mt-2 block w-full" required minlength="3" maxlength="40" pattern="[a-zA-Z0-9_]+" autocomplete="username" autocapitalize="none" :spellcheck="false" placeholder="Choose a username" :aria-invalid="Boolean(form.errors.username)" aria-describedby="username-hint username-error" />
                    <p id="username-hint" class="mt-2 text-xs leading-5 text-muted">3–40 letters, numbers, or underscores.</p>
                    <InputError id="username-error" class="mt-2" :message="form.errors.username" />
                </div>
            </div>
            <div>
                <InputLabel for="email" value="Email address" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full" required maxlength="255" autocomplete="email" autocapitalize="none" :spellcheck="false" placeholder="you@example.com" :aria-invalid="Boolean(form.errors.email)" aria-describedby="email-error" />
                <InputError id="email-error" class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <InputLabel for="password" value="Password" />
                    <button type="button" :aria-pressed="showPasswords" aria-controls="password password_confirmation" class="rounded text-xs font-semibold text-accent-text underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary" @click="showPasswords = !showPasswords">{{ showPasswords ? 'Hide passwords' : 'Show passwords' }}</button>
                </div>
                <TextInput id="password" v-model="form.password" :type="showPasswords ? 'text' : 'password'" class="block w-full" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" :aria-invalid="Boolean(form.errors.password)" aria-describedby="password-requirements password-error" />
                <PasswordRequirements id="password-requirements" :password="form.password" />
                <InputError id="password-error" class="mt-2" :message="form.errors.password" />
            </div>
            <div>
                <InputLabel for="password_confirmation" value="Confirm password" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" :type="showPasswords ? 'text' : 'password'" class="mt-2 block w-full" required minlength="8" autocomplete="new-password" placeholder="Enter your password again" :aria-invalid="Boolean(form.errors.password_confirmation)" aria-describedby="confirmation-error" />
                <InputError id="confirmation-error" class="mt-2" :message="form.errors.password_confirmation" />
            </div>
            <div class="flex items-start gap-3 rounded-xl bg-accent-soft p-4 text-accent-text">
                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" /><path d="m4 7 8 6 8-6" /></svg>
                <p class="text-xs leading-5">Next, verify your email with a six-digit code. Use an address you can access.</p>
            </div>
            <button type="submit" class="action w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary" :disabled="form.processing">{{ form.processing ? 'Creating your account…' : 'Create account' }}</button>
        </form>
        <p class="mt-6 border-t border-border pt-5 text-center text-sm text-muted">Already have an account? <Link :href="route('login')" class="rounded font-semibold text-accent-text underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">Log in</Link></p>
    </LoginLayout>
</template>
