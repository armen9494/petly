<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import SpendChart from '../../components/SpendChart.vue';
import Usd from '../../components/Usd.vue';
import { STATISTICS_DIGITS } from '../../lib/money';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    markupPercent: { type: Number, required: true },
    report: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const range = ref({ from: props.from, to: props.to });
const hideEmptyDays = ref(true);

function apply() {
    router.get('/admin/report', range.value, { preserveState: true });
}

/** Whether our provider cost for a day matches Pixelforge's usage report, to the nanodollar. */
function reconciles(row) {
    return row.pixelforge !== null && row.pixelforge !== undefined && row.pixelforge.cost_nanos === row.provider_cost_nanos;
}

const days = computed(() =>
    props.report.days.filter((day) => !hideEmptyDays.value || day.generations > 0 || (day.pixelforge?.generations ?? 0) > 0),
);

const mismatchedDays = computed(() => props.report.days.filter((day) => day.pixelforge && !reconciles(day)).length);

function margin(row) {
    return row.charged_nanos === 0 ? '—' : `${(((row.charged_nanos - row.provider_cost_nanos) / row.charged_nanos) * 100).toFixed(1)}%`;
}

const TABLE_HEAD = 'border-b border-stone-200 text-left text-xs text-stone-500';
const HEAD_CELL = 'px-4 py-3 font-medium';
const CELL = 'px-4 py-2.5';
</script>

<template>
    <div class="space-y-8">
        <Head title="Admin report" />

        <PageHeader
            title="Spend and profit"
            :description="`Succeeded generations by the UTC day they completed, which is how Pixelforge bills. Users pay cost + ${markupPercent}%.`"
        >
            <form class="flex items-end gap-2 text-sm" @submit.prevent="apply">
                <label class="block">
                    <span class="text-xs text-stone-500">From (UTC)</span>
                    <input v-model="range.from" type="date" class="mt-1 block rounded-lg border border-stone-300 bg-white px-2 py-1.5 shadow-xs" />
                </label>
                <label class="block">
                    <span class="text-xs text-stone-500">To (UTC)</span>
                    <input v-model="range.to" type="date" class="mt-1 block rounded-lg border border-stone-300 bg-white px-2 py-1.5 shadow-xs" />
                </label>
                <button type="submit" class="rounded-lg bg-stone-900 px-3 py-1.5 font-medium text-white hover:bg-stone-700">Apply</button>
            </form>
        </PageHeader>
        <p v-if="errors.from" class="text-sm text-red-600">{{ errors.from }}</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <p class="text-sm text-stone-500">Credit spend</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight"><Usd :value="report.total.charged" :digits="STATISTICS_DIGITS" /></p>
                <p class="mt-1 text-xs text-stone-400">{{ report.total.generations }} succeeded generations</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <p class="text-sm text-stone-500">Provider cost (our records)</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight"><Usd :value="report.total.provider_cost" :digits="STATISTICS_DIGITS" /></p>
                <p class="mt-1 font-mono text-xs text-stone-400">{{ report.total.provider_cost }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <p class="text-sm text-stone-500">Pixelforge says</p>
                <template v-if="report.total.pixelforge">
                    <p class="mt-1 text-2xl font-semibold tracking-tight"><Usd :value="report.total.pixelforge.cost" :digits="STATISTICS_DIGITS" /></p>
                    <p class="mt-1 text-xs" :class="reconciles(report.total) ? 'text-emerald-700' : 'text-amber-700'">
                        {{ reconciles(report.total) ? 'Matches to the nanodollar' : `${mismatchedDays} day(s) differ` }}
                    </p>
                </template>
                <p v-else class="mt-1 text-sm text-amber-700">{{ report.usage_error }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-xs">
                <p class="text-sm text-emerald-700">Profit</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-emerald-900"><Usd :value="report.total.profit" :digits="STATISTICS_DIGITS" /></p>
                <p class="mt-1 text-xs text-emerald-700">{{ margin(report.total) }} margin</p>
            </div>
        </div>

        <section aria-labelledby="chart-heading" class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="chart-heading" class="font-semibold">Daily credit spend</h2>
                <p class="text-xs text-stone-500">{{ from }} to {{ to }}, UTC</p>
            </div>
            <SpendChart :days="report.days" />
        </section>

        <section aria-labelledby="by-day-heading" class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 id="by-day-heading" class="font-semibold">By day</h2>
                <label class="flex items-center gap-2 text-sm text-stone-500">
                    <input v-model="hideEmptyDays" type="checkbox" class="size-4 accent-violet-600" />
                    Hide days without usage
                </label>
            </div>
            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-xs">
                <table class="w-full text-sm tabular-nums">
                    <thead :class="TABLE_HEAD">
                        <tr>
                            <th :class="HEAD_CELL">Date (UTC)</th>
                            <th :class="HEAD_CELL" class="text-right">Generations</th>
                            <th :class="HEAD_CELL" class="text-right">Credit spend</th>
                            <th :class="HEAD_CELL" class="text-right">Provider cost</th>
                            <th :class="HEAD_CELL" class="text-right">Pixelforge usage</th>
                            <th :class="HEAD_CELL" class="text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 font-mono text-xs">
                        <tr v-for="day in days" :key="day.date" :class="day.pixelforge && !reconciles(day) ? 'bg-amber-50' : ''">
                            <td :class="CELL" class="font-sans text-sm whitespace-nowrap">{{ day.date }}</td>
                            <td :class="CELL" class="text-right">
                                {{ day.generations }}
                                <span v-if="day.pixelforge && day.pixelforge.generations !== day.generations" class="text-amber-700">
                                    (PF {{ day.pixelforge.generations }})
                                </span>
                            </td>
                            <td :class="CELL" class="text-right">{{ day.charged }}</td>
                            <td :class="CELL" class="text-right">{{ day.provider_cost }}</td>
                            <td :class="CELL" class="text-right whitespace-nowrap">
                                <template v-if="day.pixelforge">
                                    {{ day.pixelforge.cost }}
                                    <span :class="reconciles(day) ? 'text-emerald-600' : 'text-amber-700'">{{ reconciles(day) ? '✓' : '≠' }}</span>
                                </template>
                                <span v-else class="text-stone-400">—</span>
                            </td>
                            <td :class="CELL" class="text-right">{{ day.profit }}</td>
                        </tr>
                        <tr v-if="days.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center font-sans text-sm text-stone-500">No usage in this range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="by-user-heading" class="space-y-3">
            <h2 id="by-user-heading" class="font-semibold">By user</h2>
            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-xs">
                <table class="w-full text-sm tabular-nums">
                    <thead :class="TABLE_HEAD">
                        <tr>
                            <th :class="HEAD_CELL">User</th>
                            <th :class="HEAD_CELL" class="text-right">Generations</th>
                            <th :class="HEAD_CELL" class="text-right">Credit spend</th>
                            <th :class="HEAD_CELL" class="text-right">Provider cost</th>
                            <th :class="HEAD_CELL" class="text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="row in report.users" :key="row.user?.id">
                            <td :class="CELL">
                                <span class="font-medium">{{ row.user?.name ?? 'Deleted user' }}</span>
                                <span class="block text-xs text-stone-500">{{ row.user?.email }}</span>
                            </td>
                            <td :class="CELL" class="text-right">{{ row.generations }}</td>
                            <td :class="CELL" class="text-right font-mono text-xs">{{ row.charged }}</td>
                            <td :class="CELL" class="text-right font-mono text-xs">{{ row.provider_cost }}</td>
                            <td :class="CELL" class="text-right font-mono text-xs">{{ row.profit }}</td>
                        </tr>
                        <tr v-if="report.users.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-stone-500">No usage in this range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="by-day-user-heading" class="space-y-3">
            <h2 id="by-day-user-heading" class="font-semibold">By day and user</h2>
            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-xs">
                <table class="w-full text-sm tabular-nums">
                    <thead :class="TABLE_HEAD">
                        <tr>
                            <th :class="HEAD_CELL">Date (UTC)</th>
                            <th :class="HEAD_CELL">User</th>
                            <th :class="HEAD_CELL" class="text-right">Generations</th>
                            <th :class="HEAD_CELL" class="text-right">Credit spend</th>
                            <th :class="HEAD_CELL" class="text-right">Provider cost</th>
                            <th :class="HEAD_CELL" class="text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="row in report.days_by_user" :key="`${row.date}-${row.user?.id}`">
                            <td :class="CELL" class="whitespace-nowrap">{{ row.date }}</td>
                            <td :class="CELL">{{ row.user?.email ?? 'Deleted user' }}</td>
                            <td :class="CELL" class="text-right">{{ row.generations }}</td>
                            <td :class="CELL" class="text-right font-mono text-xs">{{ row.charged }}</td>
                            <td :class="CELL" class="text-right font-mono text-xs">{{ row.provider_cost }}</td>
                            <td :class="CELL" class="text-right font-mono text-xs">{{ row.profit }}</td>
                        </tr>
                        <tr v-if="report.days_by_user.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-stone-500">No usage in this range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
