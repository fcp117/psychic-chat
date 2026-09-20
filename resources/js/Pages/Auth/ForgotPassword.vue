<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout title="Forgot your password?" description="Enter your account email and we’ll send you a link to choose a new password.">
        <Head title="Forgot Password" />

<div
            v-if="status"
            class="mb-4 text-sm font-medium text-success "
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="email"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6 flex">
                <PrimaryButton class="w-full justify-center !rounded-full !py-3.5"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Email Password Reset Link
                </PrimaryButton>
            </div>
        </form>
        <p class="mt-6 border-t border-border pt-5 text-center text-sm"><Link :href="route('login')" class="text-accent-text underline underline-offset-4">Back to login</Link></p>
    </GuestLayout>
</template>
