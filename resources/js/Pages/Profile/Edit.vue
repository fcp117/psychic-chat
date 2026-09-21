<script setup>
import UserAvatar from '@/Components/UserAvatar.vue';
import UpdateProfilePhotoForm from './Partials/UpdateProfilePhotoForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
defineProps({ mustVerifyEmail: Boolean, status: String });
const user = computed(() => usePage().props.auth.user);
const initials = computed(() => user.value.name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => Array.from(part)[0]).join('').toUpperCase());
</script>
<template>
    <Head title="Your profile" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-text">Your account</p>
            <h1 class="mt-3 font-serif text-4xl text-content">Make yourself at home.</h1>
            <p class="mt-4 max-w-xl leading-7 text-muted">Manage your details, keep your account secure, and make Psychic Chat your own.</p>
            <div class="mt-9 grid items-start gap-7 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="rounded-3xl border border-border bg-surface p-6 shadow-sm">
                    <UserAvatar :user="user" class="h-16 w-16 text-2xl" />
                    <h2 class="mt-5 break-words text-lg font-semibold">{{ user.name }}</h2>
                    <p class="mt-1 break-all text-sm text-muted">{{ user.email }}</p>
                    <span class="mt-4 inline-block rounded-full bg-accent-soft px-3 py-1 text-xs capitalize text-accent-text">{{ user.role }}</span>
                    <nav class="mt-6 space-y-2 border-t border-border pt-5" aria-label="Profile sections">
                        <a href="#profile-details" class="block rounded-xl px-3 py-3 text-sm text-content hover:bg-surface-hover">Profile information <span aria-hidden="true">↗</span></a>
                        <a href="#profile-security" class="block rounded-xl px-3 py-3 text-sm text-content hover:bg-surface-hover">Password & security <span aria-hidden="true">↗</span></a>
                        <a href="#profile-close" class="block rounded-xl px-3 py-3 text-sm text-muted hover:bg-surface-hover">Account closure <span aria-hidden="true">↗</span></a>
                    </nav>
                </aside>
                <div class="min-w-0 space-y-6">
                    <section class="rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8"><UpdateProfilePhotoForm /></section>
                    <section id="profile-details" class="scroll-mt-6 rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8"><UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" /></section>
                    <section id="profile-security" class="scroll-mt-6 rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8"><UpdatePasswordForm /></section>
                    <section id="profile-close" class="scroll-mt-6 rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8"><DeleteUserForm /></section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
