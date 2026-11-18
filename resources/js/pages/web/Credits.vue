<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CreditTransactionsTable from '../../components/CreditTransactionsTable.vue';
import PageHeader from '../../components/PageHeader.vue';
import SubmitButton from '../../components/SubmitButton.vue';
import Usd from '../../components/Usd.vue';
import { formatUsd } from '../../lib/money';

defineProps({
    packs: { type: Array, required: true },
    limits: { type: Object, required: true },
    transactions: { type: Object, required: true },
});

const page = usePage();
const credits = computed(() => page.props.credits);

/**
 * One amount, as typed or picked from a pack, so the two can't disagree:
 * picking a pack fills the field, and typing a pack's amount selects it.
 */
const form = useForm({ dollars: '' });

/**
 * The amount for the button label, once it looks like dollars and cents. The
 * server checks the amount and the range either way.
 */
const chosenAmount = computed(() => (/^\d{1,4}(\.\d{1,2})?$/.test(form.dollars) ? formatUsd(form.dollars, 2) : null));

function buy() {
    form.post('/credits', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-8">
        <Head title="Credits" />

        <PageHeader title="Credits" description="Credits are US dollars. Images are charged at their actual cost once they succeed." />

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <p class="text-sm text-stone-500">Balance</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight"><Usd :value="credits.balance" /></p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <p class="text-sm text-stone-500">Reserved</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-stone-500"><Usd :value="credits.held" /></p>
                <p class="mt-1 text-xs text-stone-400">The most your unfinished images could cost</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-5 shadow-xs">
                <p class="text-sm text-violet-700">Available to spend</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-violet-900"><Usd :value="credits.available" /></p>
            </div>
        </div>

        <form class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs" @submit.prevent="buy">
            <h2 class="font-semibold">Buy credits</h2>
            <p class="mt-1 text-sm text-stone-500">This is a demo: no payment is taken.</p>
            <fieldset class="mt-4 space-y-4" :disabled="form.processing">
                <legend class="sr-only">Amount</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <label
                        v-for="pack in packs"
                        :key="pack"
                        class="cursor-pointer rounded-lg border px-4 py-4 text-center text-lg font-semibold transition has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-violet-600 has-disabled:cursor-not-allowed has-disabled:opacity-50"
                        :class="
                            form.dollars === String(pack)
                                ? 'border-violet-500 bg-violet-50 text-violet-800 ring-1 ring-violet-500'
                                : 'border-stone-200 hover:border-violet-300 hover:bg-violet-50/50'
                        "
                    >
                        <input v-model="form.dollars" type="radio" name="pack" :value="String(pack)" class="sr-only" />
                        ${{ pack }}
                    </label>
                </div>

                <label class="block sm:max-w-xs">
                    <span class="text-sm font-medium text-stone-700">Or enter an amount</span>
                    <span
                        class="mt-1.5 flex items-center rounded-lg border bg-white shadow-xs focus-within:ring-2 focus-within:ring-violet-500/30"
                        :class="form.errors.dollars ? 'border-red-400 focus-within:border-red-500' : 'border-stone-300 focus-within:border-violet-500'"
                    >
                        <span aria-hidden="true" class="pl-3 text-sm text-stone-500">$</span>
                        <input
                            v-model.trim="form.dollars"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="15.00"
                            aria-describedby="amount-hint"
                            :aria-invalid="form.errors.dollars ? 'true' : undefined"
                            class="w-full rounded-lg bg-transparent py-2 pr-3 pl-1.5 text-sm tabular-nums outline-none placeholder:text-stone-400"
                        />
                    </span>
                    <span v-if="form.errors.dollars" class="mt-1 block text-sm text-red-600">{{ form.errors.dollars }}</span>
                    <span v-else id="amount-hint" class="mt-1 block text-xs text-stone-500">
                        Any amount from ${{ limits.min.toLocaleString() }} to ${{ limits.max.toLocaleString() }}, in dollars and cents.
                    </span>
                </label>
            </fieldset>
            <div class="mt-4 flex justify-end">
                <SubmitButton :processing="form.processing" processing-label="Buying…" :disabled="form.dollars === ''" class="sm:w-auto sm:px-6">
                    <template v-if="chosenAmount">Buy {{ chosenAmount }} of credits</template>
                    <template v-else>Choose an amount</template>
                </SubmitButton>
            </div>
        </form>

        <section aria-labelledby="history-heading" class="space-y-4">
            <h2 id="history-heading" class="font-semibold">History</h2>
            <CreditTransactionsTable :transactions="transactions" empty-text="No transactions yet. Buy credits to get started." />
        </section>
    </div>
</template>
