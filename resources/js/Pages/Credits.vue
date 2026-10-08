<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CreditHistoryPager from '@/Components/CreditHistoryPager.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    balance: Number,
    transactions: Object,
    shop: Object,
    packages: Array,
    paymentMethods: Array,
    purchases: Object,
});

const credits = (units) => (Number(units) / 3600).toLocaleString(undefined, { maximumFractionDigits: 4 });

const pesos = (cents) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cents / 100);

const selected = ref(props.packages[0]?.id ?? null);
const item = computed(() => props.packages.find((p) => p.id === selected.value));
const count = computed(() => (item.value ? item.value.credits : 0));
const total = computed(() => (item.value ? item.value.amount : 0));
const canBuy = computed(() => usePage().props.auth.user.role === 'user');

const form = useForm({
    package_id: null,
    credits: null,
    provider: 'paymongo',
    request_key: crypto.randomUUID(),
    expected_amount: 0,
    expected_credits: 0,
});

const method = computed(() => props.paymentMethods.find((p) => p.id === form.provider));

const valid = computed(
    () =>
        canBuy.value &&
        item.value &&
        Number.isInteger(count.value) &&
        count.value >= 1 &&
        count.value <= 100000 &&
        total.value >= Math.max(props.shop.minimum_amount, method.value?.minimum ?? 100) &&
        total.value <= 10000000 &&
        method.value?.available
);

watch(selected, () => {
    form.request_key = crypto.randomUUID();
    form.clearErrors();
});

const buy = () => {
    if (!valid.value) return;
    form.package_id = item.value.id;
    form.credits = null;
    form.expected_amount = total.value;
    form.expected_credits = count.value;
    form.post(route('credits.checkout'));
};
</script>

<template>
    <Head title="Credits" />

    <AuthenticatedLayout>
        <section class="mx-auto max-w-7xl px-5 py-12">
            <p class="text-xs uppercase tracking-widest text-accent-text">Your reading balance</p>
            <h1 class="mt-3 font-serif text-4xl">Credits</h1>

            <div class="my-8 overflow-hidden rounded-3xl border border-primary/20 bg-accent-soft p-7 shadow-sm sm:flex sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-accent-text">Reading balance</p>
                    <p class="mt-3 font-serif text-5xl">
                        {{ balance.toLocaleString(undefined, { maximumFractionDigits: 4 }) }}
                    </p>
                    <p class="mt-2 text-sm text-muted">Use credits when a spiritual coach accepts your reading request.</p>
                </div>
                <div class="mt-5 rounded-2xl border border-primary/20 bg-surface/80 px-4 py-3 text-sm sm:mt-0">
                    <p class="font-semibold text-accent-text">Pay only for what you choose</p>
                    <p class="mt-1 text-xs text-muted">Secure checkout is handled by PayMongo.</p>
                </div>
            </div>

            <!-- Credit Purchase Section -->
            <section v-if="canBuy" class="mb-12">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-serif text-3xl">Add credits</h2>
                </div>
                <p class="mt-3 text-sm text-muted">
                    Choose a package. Your chat time depends on the spiritual coach’s displayed hourly rate.
                </p>

                <p class="mb-3 mt-6 text-sm font-semibold text-content">Choose a package</p>
                <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <fieldset class="min-w-0">
                    <legend class="sr-only">Choose a package</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="pack in packages"
                            :key="pack.id"
                            class="relative cursor-pointer overflow-hidden rounded-2xl border bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            :class="selected === pack.id ? 'border-primary ring-2 ring-primary/30' : 'border-border'"
                        >
                            <span v-if="pack.name === 'Most popular'" class="absolute right-12 top-0 rounded-bl-xl bg-primary px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white">Popular</span>
                            <span v-if="pack.name === 'Best value'" class="absolute right-0 top-0 rounded-bl-xl bg-accent-text px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white">Best value</span>
                            <div class="flex justify-between gap-3">
                                <span class="font-semibold">{{ pack.name }}</span>
                                <input
                                    type="radio"
                                    name="credit-option"
                                    :value="pack.id"
                                    v-model="selected"
                                />
                            </div>
                            <p class="mt-4 text-xl">
                                {{ pack.credits.toLocaleString() }}
                                <span class="text-sm text-muted">credits</span>
                            </p>
                            <p class="mt-2 font-semibold text-accent-text">{{ pesos(pack.amount) }}</p>
                            <p class="mt-1 text-xs text-muted">{{ pesos(Math.round(pack.amount / pack.credits)) }} per credit</p>
                        </label>

                    </div>
                    <p v-if="!packages.length" class="mt-5 text-muted">
                    Credit purchases are currently unavailable.
                    </p>
                </fieldset>

                <form @submit.prevent="buy" class="space-y-4 rounded-3xl border border-primary/20 bg-surface p-5 shadow-lg shadow-primary/10 lg:sticky lg:top-24">
                    <div class="flex items-center justify-between gap-3"><span class="rounded-lg bg-white px-3 py-2"><img src="https://cdn.prod.website-files.com/678878af11b661d623a19735/678878af11b661d623a19a81_PayMongo-Logo-Horizontal-MongoGreen-2025%403x.png" alt="PayMongo" class="h-5 w-auto max-w-28 object-contain" /></span><h3 class="text-xs font-semibold uppercase tracking-widest text-accent-text">Secure checkout</h3></div>
                    <div
                        v-if="count > 0 && item"
                        class="rounded-2xl bg-accent-soft p-4"
                    >
                        <p class="text-sm text-muted">{{ item.name }} · {{ Number(count).toLocaleString() }} credits</p>
                        <strong class="mt-1 block text-xl text-content">{{ pesos(total) }} total</strong>
                    </div>

                    <p class="text-xs text-muted">Cards and e-wallets through PayMongo.</p>
                    <p v-if="!method?.available" role="status" class="text-sm text-muted">
                        Checkout is currently unavailable. Please try again later.
                    </p>

                    <p
                        v-if="total < Math.max(shop.minimum_amount, method?.minimum ?? 100)"
                        class="text-sm text-error"
                    >
                        Choose enough credits to meet the minimum of {{ pesos(Math.max(shop.minimum_amount, method?.minimum ?? 100)) }}.
                    </p>

                    <p v-for="e in form.errors" :key="e" role="alert" class="text-sm text-error">
                        {{ e }}
                    </p>

                    <button class="action w-full" :disabled="!valid || form.processing">
                        {{ form.processing ? 'Opening checkout…' : 'Continue to payment' }}
                    </button>

                    <p class="text-xs leading-5 text-muted">
                        Credits are added after payment confirmation.
                        <span v-if="method?.mode === 'sandbox'" class="block">No real money will be charged.</span>
                    </p>
                </form>
                </div>
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-2">
                <section class="min-w-0 overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="purchases-title">
                    <header class="border-b border-border px-5 py-4"><h2 id="purchases-title" class="font-serif text-2xl">Recent purchases</h2><p class="mt-1 text-xs text-muted">Your checkout requests and payment status.</p></header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">Credit purchases, five per page</caption>
                            <thead class="bg-page text-xs text-muted"><tr><th scope="col" class="px-5 py-3 font-medium">Package</th><th scope="col" class="px-4 py-3 text-right font-medium">Total</th><th scope="col" class="px-5 py-3 font-medium">Status</th></tr></thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="purchase in purchases.data" :key="purchase.id" class="hover:bg-surface-hover">
                                    <td class="px-5 py-4"><Link :href="route('credits.purchase', purchase.id)" class="font-semibold text-accent-text underline decoration-primary/30 underline-offset-4">{{ purchase.label }}</Link><p class="mt-1 text-xs text-muted">{{ purchase.credits }} credits</p></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right font-medium">{{ pesos(purchase.amount) }}</td>
                                    <td class="px-5 py-4"><span class="inline-block whitespace-nowrap rounded-full bg-accent-soft px-2.5 py-1 text-xs capitalize text-accent-text">{{ purchase.status.replaceAll('_', ' ') }}</span></td>
                                </tr>
                                <tr v-if="!purchases.data.length"><td colspan="3" class="px-5 py-10 text-center text-muted">No purchases yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <CreditHistoryPager :data="purchases" label="Purchase pages" />
                </section>
                <section class="min-w-0 overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="transactions-title">
                    <header class="border-b border-border px-5 py-4"><h2 id="transactions-title" class="font-serif text-2xl">Transaction history</h2><p class="mt-1 text-xs text-muted">Credits added, used, or adjusted in your account.</p></header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">Credit transactions, five per page</caption>
                            <thead class="bg-page text-xs text-muted"><tr><th scope="col" class="px-5 py-3 font-medium">Activity</th><th scope="col" class="px-4 py-3 text-right font-medium">Credits</th><th scope="col" class="px-5 py-3 text-right font-medium">Balance</th></tr></thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-surface-hover">
                                    <td class="max-w-xs px-5 py-4"><p class="font-medium capitalize">{{ t.kind.replaceAll('_', ' ') }}</p><p class="mt-1 line-clamp-2 break-words text-xs text-muted" :title="t.reason">{{ t.reason }}</p><p class="mt-1 text-[10px] text-muted">{{ t.created_at }} UTC</p></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-accent-text">{{ credits(t.amount_units) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-muted">{{ credits(t.balance_units) }}</td>
                                </tr>
                                <tr v-if="!transactions.data.length"><td colspan="3" class="px-5 py-10 text-center text-muted">No credit activity yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <CreditHistoryPager :data="transactions" label="Transaction pages" />
                </section>
            </div>
        </section>

    </AuthenticatedLayout>
</template>
