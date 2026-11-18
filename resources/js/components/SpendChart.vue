<script setup>
import { computed, ref } from 'vue';
import { formatUsd, fromNanos, STATISTICS_DIGITS } from '../lib/money';

/**
 * Daily credit spend as stacked bars: what Pixelforge cost us, plus our profit
 * on top, so each bar's height is what users were charged. The "By day" table
 * below it on the report carries the same numbers for screen readers.
 */
const props = defineProps({
    days: { type: Array, required: true },
});

const SERIES = {
    cost: { label: 'Provider cost', color: '#6250d6' },
    profit: { label: 'Profit', color: '#e87ba4' },
};

const hovered = ref(null);

// Oldest first, so time runs left to right whatever order the report lists days in.
const bars = computed(() =>
    [...props.days].sort((a, b) => a.date.localeCompare(b.date)).map((day) => {
        const cost = Math.max(day.provider_cost_nanos, 0);
        const profit = Math.max(day.charged_nanos - day.provider_cost_nanos, 0);

        return { ...day, cost, profit, total: cost + profit };
    }),
);

const totals = computed(() => ({
    cost: bars.value.reduce((sum, bar) => sum + bar.cost, 0),
    profit: bars.value.reduce((sum, bar) => sum + bar.profit, 0),
}));

/**
 * The top of the axis: the tallest bar rounded up to four even, readable steps.
 */
const axis = computed(() => {
    const tallest = Math.max(...bars.value.map((bar) => bar.total), 0);

    if (tallest === 0) {
        return { top: 1, ticks: [0] };
    }

    const roughStep = tallest / 4;
    const magnitude = 10 ** Math.floor(Math.log10(roughStep));
    const step = [1, 2, 2.5, 5, 10].map((factor) => factor * magnitude).find((candidate) => candidate >= roughStep);

    return { top: step * 4, ticks: [0, 1, 2, 3, 4].map((index) => index * step) };
});

const isEmpty = computed(() => axis.value.ticks.length === 1);

const labelledIndexes = computed(() => [...new Set([0, Math.floor((bars.value.length - 1) / 2), bars.value.length - 1])]);

const percentOfAxis = (nanos) => `${(nanos / axis.value.top) * 100}%`;
const money = (nanos) => formatUsd(fromNanos(nanos), STATISTICS_DIGITS);
const tickLabel = (nanos) => formatUsd(fromNanos(nanos));

function shortDate(date) {
    return new Date(`${date}T00:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
}

function tooltipAlignment(index) {
    const position = (index + 0.5) / bars.value.length;

    if (position < 0.2) {
        return 'left-0';
    }

    return position > 0.8 ? 'right-0' : 'left-1/2 -translate-x-1/2';
}
</script>

<template>
    <div>
        <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <li v-for="(series, key) in SERIES" :key="key" class="flex items-center gap-2">
                <span class="size-2.5 rounded-[3px]" :style="{ backgroundColor: series.color }"></span>
                <span class="text-stone-600">{{ series.label }}</span>
                <span class="font-medium text-stone-900 tabular-nums">{{ money(totals[key]) }}</span>
            </li>
        </ul>

        <div
            class="relative mt-6 h-56 sm:h-64"
            role="img"
            :aria-label="`Daily credit spend split into provider cost (${money(totals.cost)}) and profit (${money(totals.profit)}). The By day table has each day's numbers.`"
        >
            <div
                v-for="tick in axis.ticks"
                :key="tick"
                class="absolute inset-x-0 flex h-0 items-center gap-3"
                :style="{ bottom: percentOfAxis(tick) }"
            >
                <span class="w-14 text-right text-[11px] text-stone-500 tabular-nums">{{ tickLabel(tick) }}</span>
                <span class="h-px flex-1" :class="tick === 0 ? 'bg-stone-300' : 'bg-stone-100'"></span>
            </div>

            <p v-if="isEmpty" class="absolute inset-0 left-[4.25rem] grid place-items-center text-sm text-stone-500">No usage in this range.</p>

            <div v-else class="absolute inset-y-0 right-0 left-[4.25rem] flex items-end gap-[3px]" @mouseleave="hovered = null">
                <div
                    v-for="(bar, index) in bars"
                    :key="bar.date"
                    class="relative flex h-full flex-1 items-end justify-center"
                    @mouseenter="hovered = index"
                >
                    <div
                        class="flex w-full max-w-6 flex-col gap-0.5 transition-opacity duration-200"
                        :class="hovered !== null && hovered !== index ? 'opacity-35' : ''"
                        :style="{ height: percentOfAxis(bar.total) }"
                    >
                        <div
                            v-if="bar.profit > 0"
                            class="min-h-0.5 rounded-t-[4px]"
                            :style="{ flexGrow: bar.profit, backgroundColor: SERIES.profit.color }"
                        ></div>
                        <div
                            v-if="bar.cost > 0"
                            class="min-h-0.5"
                            :class="bar.profit === 0 && 'rounded-t-[4px]'"
                            :style="{ flexGrow: bar.cost, backgroundColor: SERIES.cost.color }"
                        ></div>
                    </div>

                    <div
                        v-if="hovered === index"
                        class="pointer-events-none absolute z-10 mb-2 w-52 rounded-xl border border-stone-200 bg-white p-3 text-xs shadow-lg"
                        :class="tooltipAlignment(index)"
                        :style="{ bottom: percentOfAxis(bar.total) }"
                    >
                        <p class="font-medium text-stone-900">{{ shortDate(bar.date) }}</p>
                        <p class="text-stone-500">{{ bar.generations }} generation{{ bar.generations === 1 ? '' : 's' }}</p>
                        <dl class="mt-2 space-y-1">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-stone-600">Credit spend</dt>
                                <dd class="font-medium text-stone-900 tabular-nums">{{ money(bar.charged_nanos) }}</dd>
                            </div>
                            <div v-for="(series, key) in SERIES" :key="key" class="flex items-center justify-between gap-3">
                                <dt class="flex items-center gap-1.5 text-stone-600">
                                    <span class="size-2 rounded-[2px]" :style="{ backgroundColor: series.color }"></span>
                                    {{ series.label }}
                                </dt>
                                <dd class="text-stone-900 tabular-nums">{{ money(bar[key]) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!isEmpty" class="relative mt-3 ml-[4.25rem] h-4 text-[11px] text-stone-500">
            <span
                v-for="index in labelledIndexes"
                :key="index"
                class="absolute whitespace-nowrap"
                :class="index === 0 ? 'left-0' : index === bars.length - 1 ? 'right-0' : '-translate-x-1/2'"
                :style="index !== 0 && index !== bars.length - 1 ? { left: `${((index + 0.5) / bars.length) * 100}%` } : {}"
            >
                {{ shortDate(bars[index].date) }}
            </span>
        </div>
    </div>
</template>
