<script setup>
import { computed, ref } from 'vue';
import { useClipboard } from '../lib/clipboard';
import { formatDateTime } from '../lib/time';
import Icon from './Icon.vue';
import Usd from './Usd.vue';

/**
 * One finished image shown large, with its details. Built on <dialog>, so
 * focus handling, Escape and stacking above the page come from the browser.
 */
const props = defineProps({
    generation: { type: Object, required: true },
});

const dialog = ref(null);
const { copied, copy } = useClipboard();

const g = computed(() => props.generation);

const tokenRows = computed(() =>
    g.value.tokens === null
        ? []
        : [
              ['Prompt text', g.value.tokens.input_text],
              ['Reference images', g.value.tokens.input_image],
              ['Output', g.value.tokens.output],
              ['Reasoning', g.value.tokens.reasoning],
          ].filter(([, count]) => count !== null && count !== undefined),
);

function open() {
    dialog.value?.showModal();
}

function close() {
    dialog.value?.close();
}

defineExpose({ open });
</script>

<template>
    <dialog
        ref="dialog"
        class="fixed inset-0 m-0 size-full max-h-none max-w-none bg-transparent p-4 backdrop:bg-stone-900/60 sm:p-8"
        :aria-label="`Image: ${g.prompt}`"
        @click.self="close"
    >
        <div class="flex size-full items-center justify-center" @click.self="close">
            <div
                class="grid max-h-full w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-xl lg:grid-cols-[minmax(0,1fr)_20rem] lg:overflow-hidden"
            >
                <div class="grid min-h-64 place-items-center bg-stone-100">
                    <img :src="g.image_url" :alt="g.prompt" class="max-h-[80vh] w-full object-contain" />
                </div>

                <div class="flex flex-col gap-5 p-5 text-sm lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-semibold text-stone-900">Image details</h2>
                        <button type="button" class="-m-1 rounded-md p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" aria-label="Close" @click="close">
                            <Icon name="x" class="size-5" />
                        </button>
                    </div>

                    <div>
                        <p class="break-words text-stone-800">{{ g.prompt }}</p>
                        <button
                            type="button"
                            class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-violet-700 hover:text-violet-900"
                            @click="copy(g.prompt)"
                        >
                            <Icon :name="copied ? 'check' : 'copy'" class="size-3.5" />
                            {{ copied ? 'Copied' : 'Copy prompt' }}
                        </button>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-stone-100 pt-4">
                        <div>
                            <dt class="text-xs text-stone-500">Size</dt>
                            <dd class="text-stone-800">{{ g.size_label }} {{ g.size }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Quality</dt>
                            <dd class="text-stone-800 capitalize">{{ g.quality }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Created</dt>
                            <dd class="text-stone-800">{{ formatDateTime(g.created_at) }}</dd>
                        </div>
                        <div v-if="g.completed_at">
                            <dt class="text-xs text-stone-500">Completed</dt>
                            <dd class="text-stone-800">{{ formatDateTime(g.completed_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Estimate</dt>
                            <dd class="text-stone-700"><Usd :value="g.estimated_min_price" /> – <Usd :value="g.estimated_max_price" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Actual cost</dt>
                            <dd class="font-semibold text-stone-900"><Usd :value="g.price" /></dd>
                        </div>
                    </dl>

                    <div v-if="tokenRows.length" class="border-t border-stone-100 pt-4">
                        <h3 class="text-xs font-medium text-stone-500">Tokens</h3>
                        <dl class="mt-2 space-y-1.5">
                            <div v-for="[label, count] in tokenRows" :key="label" class="flex justify-between gap-4">
                                <dt class="text-stone-600">{{ label }}</dt>
                                <dd class="text-stone-900 tabular-nums">{{ count.toLocaleString() }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="g.reference_images.length" class="border-t border-stone-100 pt-4">
                        <h3 class="text-xs font-medium text-stone-500">References</h3>
                        <ul class="mt-2 space-y-1.5">
                            <li v-for="reference in g.reference_images" :key="reference">
                                <a :href="reference" target="_blank" rel="noopener noreferrer" class="block truncate text-violet-700 hover:underline">{{ reference }}</a>
                            </li>
                        </ul>
                    </div>

                    <a
                        :href="g.image_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-auto inline-flex items-center justify-center gap-2 rounded-lg border border-stone-200 px-4 py-2 font-medium text-stone-700 shadow-xs hover:border-stone-300 hover:text-stone-900"
                    >
                        <Icon name="external" class="size-4" />
                        Open full size
                    </a>
                </div>
            </div>
        </div>
    </dialog>
</template>
