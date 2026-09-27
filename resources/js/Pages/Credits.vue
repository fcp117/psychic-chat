<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageLinks from '@/Components/PageLinks.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    balance: Number,
    transactions: Object,
    shop: Object,
    packages: Array,
    paymentMethods: Array,
    purchases: Array,
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
    provider: '',
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

watch([selected, () => form.provider], () => {
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
                    <p class="mt-2 text-sm text-muted">Use credits when a spiritual advisor accepts your reading request.</p>
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
                    <span class="rounded-full bg-accent-soft px-3 py-1 text-xs text-accent-text">
                        Sandbox · no real payments
                    </span>
                </div>
                <p class="mt-3 text-sm text-muted">
                    Choose a package. Your chat time depends on the spiritual advisor’s displayed hourly rate.
                </p>

                <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <fieldset>
                    <legend class="mb-3 text-sm font-semibold text-content">Choose a package</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="pack in packages"
                            :key="pack.id"
                            class="relative cursor-pointer overflow-hidden rounded-2xl border bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            :class="selected === pack.id ? 'border-primary ring-2 ring-primary/30' : 'border-border'"
                        >
                            <span v-if="pack.name === 'Most popular'" class="absolute right-0 top-0 rounded-bl-xl bg-primary px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white">Popular</span>
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

                <form @submit.prevent="buy" class="space-y-5 rounded-3xl border border-primary/20 bg-surface p-6 shadow-lg shadow-primary/10 lg:sticky lg:top-24">
                    <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-soft text-xl text-accent-text">↗</span><div><p class="text-xs font-semibold uppercase tracking-widest text-accent-text">Secure checkout</p><h3 class="font-serif text-2xl text-content">PayMongo</h3></div></div>
                    <div
                        v-if="count > 0 && item"
                        class="rounded-2xl bg-accent-soft p-4"
                    >
                        <p class="text-sm text-muted">{{ item.name }} · {{ Number(count).toLocaleString() }} credits</p>
                        <strong class="mt-1 block text-xl text-content">{{ pesos(total) }} total</strong>
                    </div>

                    <!-- Payment Methods Grid -->
                    <fieldset>
                        <legend class="mb-3 text-sm font-semibold">Payment method</legend>
                        <div class="grid gap-3">
                            <label
                                v-for="pay in paymentMethods"
                                :key="pay.id"
                                class="rounded-xl border border-border p-4"
                                :class="pay.available ? 'cursor-pointer' : 'opacity-60'"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-2 text-sm font-semibold"><img v-if="pay.id === 'paymongo'" src="https://cdn.prod.website-files.com/678878af11b661d623a19735/678878af11b661d623a19a81_PayMongo-Logo-Horizontal-MongoGreen-2025%403x.png" alt="PayMongo" class="h-5 w-auto max-w-28 object-contain" /> <span v-else>{{ pay.name }}</span></span>
                                    <input
                                        type="radio"
                                        name="payment-method"
                                        :value="pay.id"
                                        v-model="form.provider"
                                        :disabled="!pay.available"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-muted">
                                    {{ pay.available ? 'Cards and e-wallets at PayMongo checkout' : 'Not connected yet' }}
                                </p>
                                <p v-if="pay.minimum > shop.minimum_amount" class="mt-1 text-xs text-muted">
                                    Minimum {{ pesos(pay.minimum) }}
                                </p>
                            </label>
                        </div>
                    </fieldset>

                    <p v-if="!paymentMethods.some((p) => p.available)" class="text-sm text-muted">
                        Online payment is being set up. Please try again later.
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
                        {{ form.processing ? 'Opening checkout…' : 'Continue to test payment' }}
                    </button>

                    <p class="text-xs text-muted">
                        Credits are added only after the payment provider confirms success.
                    </p>
                </form>
                </div>
            </section>

            <!-- Recent Purchases -->
            <section v-if="purchases.length" class="mb-10">
                <h2 class="mb-4 font-serif text-2xl">Recent purchases</h2>
                <Link
                    v-for="purchase in purchases"
                    :key="purchase.id"
                    :href="route('credits.purchase', purchase.id)"
                    class="mb-3 flex flex-wrap justify-between gap-3 rounded-xl border border-border bg-surface p-4"
                >
                    <span>{{ purchase.label }} · {{ purchase.credits }} credits</span>
                    <span class="text-sm text-muted">
                        {{ pesos(purchase.amount) }} · {{ purchase.status.replaceAll('_', ' ') }} →
                    </span>
                </Link>
            </section>

            <!-- Transaction History -->
            <h2 class="mb-5 font-serif text-2xl">Transaction history</h2>
            <article
                v-for="t in transactions.data"
                :key="t.id"
                class="mb-3 flex flex-wrap justify-between gap-4 rounded-xl border border-border bg-surface p-5"
            >
                <div>
                    <p class="capitalize">{{ t.kind.replaceAll('_', ' ') }}</p>
                    <p class="text-sm text-muted">{{ t.reason }}</p>
                    <p class="text-xs text-muted">{{ t.created_at }} UTC</p>
                </div>
                <div class="text-right">
                    <p>{{ credits(t.amount_units) }} credits</p>
                    <p class="text-xs text-muted">Balance after: {{ credits(t.balance_units) }}</p>
                </div>
            </article>

            <PageLinks :data="transactions" />
        </section>
    </AuthenticatedLayout>
</template>
