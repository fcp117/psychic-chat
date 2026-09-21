<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UserAvatar.vue';
import InputError from '@/Components/InputError.vue';
const user = computed(() => usePage().props.auth.user);
const form = useForm({ photo: null });
const removal = useForm({});
const input = ref(null);
const preview = ref(null);
const busy = computed(() => form.processing || removal.processing);
function reset() { if (preview.value) URL.revokeObjectURL(preview.value); preview.value = null; form.reset(); if (input.value) input.value.value = ''; }
function choose(event) {
    const file = event.target.files?.[0];
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null; form.reset(); form.clearErrors();
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
        form.setError('photo', 'Choose a JPG, PNG, or WebP image no larger than 2 MB.'); return;
    }
    form.photo = file; preview.value = URL.createObjectURL(file);
}
function save() { form.post(route('profile.photo.store'), { forceFormData: true, preserveScroll: true, onSuccess: reset }); }
function remove() { removal.delete(route('profile.photo.destroy'), { preserveScroll: true, onSuccess: reset }); }
onBeforeUnmount(() => { if (preview.value) URL.revokeObjectURL(preview.value); });
</script>
<template>
    <form @submit.prevent="save" class="space-y-5">
        <div><h2 class="text-lg font-semibold text-content">Profile photo</h2><p class="mt-2 text-sm text-muted">Choose a photo to personalize your account. JPG, PNG, or WebP, up to 2 MB and 4096 × 4096 pixels.</p></div>
        <div class="flex flex-wrap items-center gap-5">
            <UserAvatar :user="{ ...user, profile_photo_url: preview || user.profile_photo_url }" class="h-20 w-20 text-2xl" />
            <div class="min-w-0 flex-1"><label for="profile-photo" class="mb-2 block text-sm font-medium">Choose photo</label><input id="profile-photo" ref="input" type="file" accept="image/jpeg,image/png,image/webp" :disabled="busy" class="block w-full max-w-full text-sm text-muted file:mr-3 file:rounded-full file:border-0 file:bg-accent-soft file:px-4 file:py-2 file:text-accent-text" @change="choose" /></div>
        </div>
        <InputError :message="form.errors.photo || form.errors.request || removal.errors.request" />
        <p v-if="form.progress" class="text-sm text-muted" role="status">Uploading… {{ form.progress.percentage }}%</p>
        <div class="flex flex-wrap gap-3">
            <button type="submit" :disabled="!form.photo || busy" class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Saving…' : 'Save photo' }}</button>
            <button v-if="form.photo" type="button" :disabled="busy" class="rounded-full border border-border px-5 py-2 text-sm" @click="reset">Cancel</button>
            <button v-if="user.profile_photo_url" type="button" :disabled="busy" class="rounded-full border border-border px-5 py-2 text-sm text-muted disabled:opacity-50" @click="remove">{{ removal.processing ? 'Removing…' : 'Remove photo' }}</button>
        </div>
    </form>
</template>