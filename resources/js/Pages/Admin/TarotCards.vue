<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminWorkspace from '@/Components/AdminWorkspace.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({ cards: Array });
const page = usePage();
const search = ref('');
const status = ref('all');
const editing = ref(null);
const open = ref(false);
const previewUrl = ref('');
let objectUrl;
const form = useForm({ name: '', category: 'Major Arcana', keywords: '', meaning: '', guidance: '', reflection: '', is_active: false, image: null });
const visible = computed(() => props.cards.filter(card => (status.value === 'all' || card.is_active === (status.value === 'active')) && `${card.name} ${card.keywords}`.toLowerCase().includes(search.value.toLowerCase())));
const activeCount = computed(() => props.cards.filter(card => card.is_active).length);
function releasePreview() { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; }
function edit(card = null) {
    releasePreview();
    editing.value = card;
    form.reset(); form.clearErrors();
    if (card) for (const key of ['name','category','keywords','meaning','guidance','reflection','is_active']) form[key] = card[key];
    previewUrl.value = card?.image_url || '';
    open.value = true;
}
function upload(event) {
    releasePreview();
    form.image = event.target.files[0] || null;
    previewUrl.value = form.image ? (objectUrl = URL.createObjectURL(form.image)) : editing.value?.image_url || '';
}
function save() {
    form.post(editing.value ? route('admin.tarot.update', editing.value.id) : route('admin.tarot.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => { open.value = false; releasePreview(); } });
}
onUnmounted(releasePreview);
</script>

<template>
    <Head title="Tarot card library" />
    <AuthenticatedLayout>
        <AdminWorkspace section="tarot">
            <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm text-muted">{{ cards.length }} cards in your library · {{ activeCount }} available to draw</p><p class="mt-1 text-xs text-muted">Guests: 1 daily draw · Members: 3 · Resets at midnight Philippine time</p></div><div class="flex gap-3"><Link :href="route('tarot.index')" class="rounded-full border border-border px-4 py-2.5 text-sm">View reading page ↗</Link><button type="button" class="action" @click="edit()">+ Add card</button></div></div>
            <p v-if="page.props.flash?.success" role="status" class="mt-5 rounded-xl bg-accent-soft p-4 text-sm text-accent-text">{{ page.props.flash.success }}</p>
            <div class="mt-6 flex flex-wrap gap-3"><label class="min-w-0 flex-1"><span class="sr-only">Search cards</span><input v-model="search" type="search" placeholder="Search names or keywords…" class="w-full rounded-xl border-border bg-page text-content" /></label><label><span class="sr-only">Card status</span><select v-model="status" class="rounded-xl border-border bg-page text-content"><option value="all">All cards</option><option value="active">Active cards</option><option value="inactive">Inactive cards</option></select></label></div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="card in visible" :key="card.id" class="overflow-hidden rounded-2xl border border-border bg-page">
                    <div class="relative bg-page p-5"><img :src="card.image_url" :alt="card.name" class="mx-auto aspect-[2/3] w-full max-w-[190px] rounded-xl object-cover shadow-xl" loading="lazy" width="1024" height="1536" /><span class="absolute right-3 top-3 rounded-full border border-border bg-surface px-3 py-1 text-[10px] font-semibold" :class="card.is_active ? 'text-accent-text' : 'text-muted'">{{ card.is_active ? 'Active' : 'Inactive' }}</span></div>
                    <div class="border-t border-border p-5"><p class="text-[10px] uppercase tracking-widest text-muted">{{ card.category }}</p><h3 class="mt-2 break-words font-serif text-2xl">{{ card.name }}</h3><p class="mt-2 line-clamp-2 text-xs leading-5 text-muted">{{ card.keywords }}</p><button type="button" class="mt-4 w-full rounded-xl border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-accent-text hover:bg-surface-hover" @click="edit(card)">Preview & edit</button></div>
                </article>
            </div>
            <p v-if="!visible.length" class="py-12 text-center text-muted">No cards found. Add a card or change your filters.</p>
            <p class="mt-6 text-xs leading-6 text-muted">Only active cards can be drawn. Editing or deactivating a card does not change saved readings. Upload only artwork you own or have permission to use.</p>
        </AdminWorkspace>
        <Modal :show="open" :closeable="!form.processing" max-width="2xl" @close="open = false">
            <form class="p-5 sm:p-7" @submit.prevent="save">
                <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] uppercase tracking-widest text-accent-text">Card studio</p><h2 class="mt-2 font-serif text-3xl">{{ editing ? 'Edit your card' : 'Create a card' }}</h2></div><button type="button" :disabled="form.processing" aria-label="Close card editor" class="rounded-full px-3 py-2 text-muted" @click="open = false">✕</button></div>
                <div class="mt-6 grid gap-6 sm:grid-cols-[150px_minmax(0,1fr)]">
                    <div><img v-if="previewUrl" :src="previewUrl" alt="Card artwork preview" class="mx-auto aspect-[2/3] w-[150px] rounded-xl object-cover shadow-lg" /><div v-else class="mx-auto grid aspect-[2/3] w-[150px] place-items-center rounded-xl border border-dashed border-border bg-page text-4xl text-accent-text">✧</div><p class="mt-3 break-words text-center font-serif text-lg">{{ form.name || 'Your card name' }}</p><p class="mt-1 text-center text-[10px] text-muted">Upright · {{ form.is_active ? 'Active' : 'Inactive' }}</p></div>
                    <div class="space-y-4"><label class="block text-sm">Card name<input v-model="form.name" required maxlength="100" class="mt-1 w-full rounded-xl border-border bg-page" /></label><label class="block text-sm">Category / suit<input v-model="form.category" required maxlength="60" placeholder="Major Arcana, Cups, Wands…" class="mt-1 w-full rounded-xl border-border bg-page" /></label><label class="block text-sm">Artwork<input type="file" accept="image/jpeg,image/png,image/webp" :required="!editing" class="mt-2 block w-full text-xs file:mr-2 file:rounded-full file:border-0 file:bg-accent-soft file:px-3 file:py-2 file:text-accent-text" @change="upload" /><span class="mt-2 block text-xs text-muted">JPG, PNG or WebP. Up to 5 MB / 4096px. Portrait 2:3 recommended.</span></label></div>
                </div>
                <div class="mt-5 space-y-4"><label class="block text-sm">Keywords<input v-model="form.keywords" required maxlength="255" placeholder="Hope, Renewal, Trust" class="mt-1 w-full rounded-xl border-border bg-page" /></label><label class="block text-sm">Card meaning<textarea v-model="form.meaning" required maxlength="3000" rows="4" class="mt-1 w-full rounded-xl border-border bg-page"></textarea></label><label class="block text-sm">Daily guidance<textarea v-model="form.guidance" required maxlength="2000" rows="3" class="mt-1 w-full rounded-xl border-border bg-page"></textarea></label><label class="block text-sm">Reflection question<textarea v-model="form.reflection" required maxlength="1000" rows="2" class="mt-1 w-full rounded-xl border-border bg-page"></textarea></label><label class="flex items-center gap-3 rounded-xl border border-border bg-page p-4 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-border text-primary" /><span>Active — make this card available for daily draws</span></label></div>
                <div v-if="Object.keys(form.errors).length" role="alert" class="mt-4 rounded-xl bg-red-400/10 p-4 text-sm text-red-400"><p v-for="(message, key) in form.errors" :key="key">{{ message }}</p></div>
                <p v-if="form.progress" class="mt-3 text-xs text-muted" role="status">Uploading {{ form.progress.percentage }}%</p>
                <div class="mt-6 flex justify-end gap-3 border-t border-border pt-5"><button type="button" :disabled="form.processing" class="rounded-full border border-border px-5 py-2.5 text-sm" @click="open = false">Cancel</button><button type="submit" :disabled="form.processing" class="action disabled:opacity-50">{{ form.processing ? 'Saving…' : 'Save card' }}</button></div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
