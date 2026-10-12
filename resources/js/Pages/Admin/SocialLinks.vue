<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
import HomeSocialLinks from '@/Components/HomeSocialLinks.vue';
const props = defineProps({ links: Object });
const form = useForm({ facebook: props.links.facebook || '', instagram: props.links.instagram || '' });
</script>
<template>
    <Head title="Social links" />
    <AuthenticatedLayout><AdminWorkspace section="social-links">
        <form class="max-w-xl space-y-5" @submit.prevent="form.put(route('admin.social-links.update'), { preserveScroll: true })">
            <p class="text-sm leading-6 text-muted">Shown as logos below the Home buttons for everyone. Leave a link blank to hide that platform.</p>
            <p v-if="$page.props.flash?.success" role="status" class="text-sm text-accent-text">{{ $page.props.flash.success }}</p>
            <label v-for="platform in ['facebook','instagram']" :key="platform" class="block text-sm font-semibold"><span class="capitalize">{{ platform }}</span><input v-model="form[platform]" type="url" maxlength="2048" :placeholder="`https://www.${platform}.com/your-page`" class="field mt-2 w-full font-normal"/><span v-if="form.errors[platform]" role="alert" class="mt-1 block text-xs text-error">{{ form.errors[platform] }}</span></label>
            <button class="action" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save links' }}</button>
            <div class="border-t border-border pt-4"><p class="text-xs text-muted">Currently published</p><HomeSocialLinks :links="links" /></div>
        </form>
    </AdminWorkspace></AuthenticatedLayout>
</template>
