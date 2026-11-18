<script setup>
import { Link } from '@inertiajs/vue3';
import Usd from './Usd.vue';

defineProps({
    estimate: { type: Object, default: null },
    error: { type: String, default: null },
    available: { type: String, required: true },
    shortfall: { type: Boolean, default: false },
});

function range(min, max) {
    return min === max ? min.toLocaleString() : `${min.toLocaleString()}–${max.toLocaleString()}`;
}
</script>

<template>
    <div class="rounded-lg border border-stone-200 bg-stone-50 p-4" aria-live="polite">
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <template v-else-if="estimate">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-sm font-medium text-stone-600">Estimated cost</span>
                <span class="text-lg font-semibold text-stone-900"> <Usd :value="estimate.min_price" /> – <Usd :value="estimate.max_price" /> </span>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-stone-500">
                You’re charged the actual cost once the image is ready, never more than
                <Usd :value="estimate.max_price" />. Failed images are free.
            </p>

            <details class="group mt-3 text-xs text-stone-600">
                <summary class="cursor-pointer list-none font-medium text-stone-500 hover:text-stone-800">
                    <span class="group-open:hidden">Why a range?</span>
                    <span class="hidden group-open:inline">Hide breakdown</span>
                </summary>
                <p class="mt-2 text-stone-500">
                    Pixelforge bills per token. The model’s reasoning and the exact prompt token count are only known once it finishes, so we show
                    the cheapest and the most expensive outcome.
                </p>
                <table class="mt-2 w-full">
                    <tbody class="divide-y divide-stone-200">
                        <tr>
                            <td class="py-1">Prompt text tokens</td>
                            <td class="py-1 text-right tabular-nums">{{ range(estimate.tokens.min.input_text_tokens, estimate.tokens.max.input_text_tokens) }}</td>
                        </tr>
                        <tr>
                            <td class="py-1">Reference image tokens</td>
                            <td class="py-1 text-right tabular-nums">{{ estimate.tokens.max.input_image_tokens.toLocaleString() }}</td>
                        </tr>
                        <tr>
                            <td class="py-1">Output tokens (exact)</td>
                            <td class="py-1 text-right tabular-nums">{{ estimate.tokens.max.output_tokens.toLocaleString() }}</td>
                        </tr>
                        <tr>
                            <td class="py-1">Reasoning tokens</td>
                            <td class="py-1 text-right tabular-nums">{{ range(estimate.tokens.min.reasoning_tokens, estimate.tokens.max.reasoning_tokens) }}</td>
                        </tr>
                    </tbody>
                </table>
            </details>
        </template>

        <div v-else class="h-12 animate-pulse rounded-md bg-stone-200/70"></div>

        <div v-if="shortfall" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
            You have <Usd :value="available" /> available, which might not cover this.
            <Link href="/credits" class="font-medium underline">Buy credits</Link>
        </div>
    </div>
</template>
