<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
const props = defineProps({ users: Object });
const eligible = computed(() => props.users.data.filter(u => u.cleanup_eligible));
const selected = ref([]), expanded = ref(false), preview = ref(null), loading = ref(false), error = ref('');
const form = useForm({ token: '', confirmation: '' });
watch(() => props.users, () => { selected.value = []; preview.value = null; });
const allSelected = computed(() => eligible.value.length > 0 && selected.value.length === eligible.value.length);
function toggleAll(event) { selected.value = event.target.checked ? eligible.value.map(u => u.id) : []; }
async function prepare(mode) {
    loading.value = true; error.value = ''; form.reset(); form.clearErrors();
    try { const { data } = await axios.post(route('admin.cleanup.preview'), { mode, ids: selected.value }); preview.value = data; form.token = data.token; }
    catch(e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || 'Unable to prepare deletion. Refresh and try again.'; }
    finally { loading.value = false; }
}
function remove() { form.delete(route('admin.cleanup.destroy'), { onSuccess: () => { preview.value = null; selected.value = []; } }); }
</script>
<template>
    <section class="mb-6 rounded-2xl border border-border bg-surface p-5">
        <button type="button" class="font-semibold text-accent-text" :aria-expanded="expanded" @click="expanded = !expanded">Manage unverified registrations {{ expanded ? '−' : '+' }}</button>
        <div v-if="expanded" class="mt-4 space-y-4">
            <p class="text-sm text-muted">Only unverified User accounts with zero credits and no purchases, transactions, chats, applications, or profile photos are eligible. Verified accounts, admins, and counselors are protected.</p>
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" :checked="allSelected" :disabled="!eligible.length || loading" @change="toggleAll" />Select all eligible accounts on this page</label>
            <div class="grid gap-2 sm:grid-cols-2">
                <label v-for="u in eligible" :key="u.id" class="flex min-w-0 items-center gap-3 rounded-xl border border-border p-3 text-sm"><input v-model="selected" type="checkbox" :value="u.id" :disabled="loading" /><span class="min-w-0 break-words">{{ u.name }}<span class="block break-all text-xs text-muted">{{ u.email }}</span></span></label>
            </div>
            <p v-if="!eligible.length" class="text-sm text-muted">No eligible accounts on this page.</p>
            <div class="flex flex-wrap gap-3"><button type="button" class="rounded-full border border-border px-4 py-2 text-sm text-error disabled:opacity-50" :disabled="!selected.length || loading" @click="prepare('selected')">Delete selected ({{ selected.length }})</button><button type="button" class="rounded-full border border-border px-4 py-2 text-sm text-error disabled:opacity-50" :disabled="loading" @click="prepare('abandoned')">Clean up registrations older than 7 days</button></div>
            <p class="text-xs text-muted">Seven-day cleanup checks all accounts, regardless of this page’s search or filter, up to 500 per batch. Selected deletion applies only to your selection.</p>
            <p v-if="loading" role="status" class="text-sm text-muted">Checking eligibility…</p><p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
        </div>
        <Modal :show="Boolean(preview)" :closeable="!form.processing" @close="preview = null">
            <form class="space-y-5 p-6 text-content" @submit.prevent="remove">
                <h2 class="font-serif text-2xl">Permanently delete {{ preview?.count }} account(s)?</h2>
                <p class="text-sm text-muted">{{ preview?.mode === 'abandoned' ? 'These eligible registrations are at least seven days old.' : 'Only the eligible accounts in your selection are included.' }} This cannot be undone. Accounts that verify or gain protected activity before confirmation will be skipped.</p>
                <label class="block text-sm">Type DELETE to confirm<input v-model="form.confirmation" autocomplete="off" class="field mt-2 block w-full" required /></label>
                <p v-for="message in form.errors" :key="message" role="alert" class="text-sm text-error">{{ message }}</p>
                <div class="flex gap-3"><button type="button" class="rounded-full border border-border px-5 py-2" :disabled="form.processing" @click="preview = null">Cancel</button><button class="action disabled:opacity-50" :disabled="form.processing || form.confirmation !== 'DELETE'">{{ form.processing ? 'Deleting…' : 'Permanently delete' }}</button></div>
            </form>
        </Modal>
    </section>
</template>
