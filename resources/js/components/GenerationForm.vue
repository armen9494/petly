<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toNanos } from '../lib/money';
import CostEstimate from './CostEstimate.vue';
import Icon from './Icon.vue';
import SubmitButton from './SubmitButton.vue';
import Usd from './Usd.vue';

const props = defineProps({
    options: { type: Object, required: true },
});

const page = usePage();

const form = useForm({
    prompt: '',
    size: props.options.sizes[0].value,
    quality: 'medium',
    reference_images: [],
});

// Pixelforge counts Unicode characters, not UTF-16 code units, so spread the string.
const characters = computed(() => [...form.prompt.trim()].length);
const referenceCount = computed(() => form.reference_images.filter((url) => url.trim() !== '').length);
const tooLong = computed(() => characters.value > props.options.maxPromptLength);

const estimate = ref(null);
const estimateError = ref(null);
let debounce = null;
let inFlightRequest = null;

async function refreshEstimate() {
    inFlightRequest?.abort();
    inFlightRequest = new AbortController();

    const query = new URLSearchParams({
        characters: Math.min(characters.value, props.options.maxPromptLength),
        size: form.size,
        quality: form.quality,
        reference_images: Math.min(referenceCount.value, props.options.maxReferenceImages),
    });

    try {
        const response = await fetch(`/estimate?${query}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: inFlightRequest.signal,
        });
        const body = await response.json();

        if (!response.ok) {
            throw new Error(body.message ?? 'The estimate is unavailable right now.');
        }

        estimate.value = body;
        estimateError.value = null;
    } catch (error) {
        if (error.name !== 'AbortError') {
            estimateError.value = error.message;
        }
    }
}

watch(
    [characters, () => form.size, () => form.quality, referenceCount],
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(refreshEstimate, 250);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearTimeout(debounce);
    inFlightRequest?.abort();
});

const available = computed(() => page.props.credits.available);
const shortfall = computed(() => estimate.value !== null && toNanos(estimate.value.max_price) > toNanos(available.value));
const canSubmit = computed(() => form.prompt.trim() !== '' && !tooLong.value && !shortfall.value && !form.processing);

const referenceErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([field]) => field.startsWith('reference_images'))
        .map(([, message]) => message),
);

function addReference() {
    if (form.reference_images.length < props.options.maxReferenceImages) {
        form.reference_images.push('');
    }
}

function removeReference(index) {
    form.reference_images.splice(index, 1);
}

function submit() {
    form.post('/generations', {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt', 'reference_images'),
    });
}

const ASPECTS = { '1024x1024': 'aspect-square w-4', '1536x1024': 'aspect-[3/2] w-5', '1024x1536': 'aspect-[2/3] w-3' };

/**
 * Starting points for an empty prompt. Picking one only fills the textarea.
 */
const IDEAS = [
    { label: 'Lighthouse at dusk', prompt: 'A lighthouse on a sea cliff at dusk, soft watercolor' },
    { label: 'Neon koi pond', prompt: 'Neon koi circling a moonlit pond, ukiyo-e woodblock print' },
    { label: 'Treehouse nook', prompt: 'A cozy reading nook inside a treehouse, golden hour, 35mm film' },
    { label: 'Glowing greenhouse', prompt: 'Isometric tiny greenhouse full of glowing plants, 3D render' },
    { label: 'Fox in the snow', prompt: 'Portrait of a red fox in a snowy birch forest, oil painting' },
    { label: 'Station on Mars', prompt: 'Retro-futurist train station on Mars, vintage travel poster' },
];

const promptField = ref(null);

function applyIdea(prompt) {
    form.prompt = prompt;
    promptField.value?.focus();
}

function surpriseMe() {
    const others = IDEAS.filter((idea) => idea.prompt !== form.prompt);
    applyIdea(others[Math.floor(Math.random() * others.length)].prompt);
}

const shortcutKey = /mac|iphone|ipad/i.test(globalThis.navigator?.userAgentData?.platform ?? globalThis.navigator?.platform ?? '') ? '⌘' : 'Ctrl';
</script>

<template>
    <form
        class="space-y-5 rounded-xl border border-stone-200 bg-white p-5 shadow-xs [scrollbar-width:thin] lg:max-h-[calc(100dvh-10.5rem)] lg:overflow-y-auto lg:overscroll-contain"
        @submit.prevent="submit"
    >
        <div>
            <label for="prompt" class="text-sm font-medium text-stone-700">Prompt</label>
            <textarea
                id="prompt"
                ref="promptField"
                v-model="form.prompt"
                rows="5"
                placeholder="A lighthouse on a cliff at dawn, oil painting"
                :aria-invalid="form.errors.prompt || tooLong ? 'true' : undefined"
                class="mt-1.5 block w-full resize-y rounded-lg border bg-white px-3 py-2 text-sm shadow-xs outline-none placeholder:text-stone-400 focus:ring-2 focus:ring-violet-500/30"
                :class="form.errors.prompt || tooLong ? 'border-red-400' : 'border-stone-300 focus:border-violet-500'"
                @keydown.meta.enter="canSubmit && submit()"
                @keydown.ctrl.enter="canSubmit && submit()"
            ></textarea>
            <div class="mt-1 flex justify-between gap-4 text-xs">
                <span v-if="form.errors.prompt" class="text-red-600">{{ form.errors.prompt }}</span>
                <span v-else class="text-stone-400">{{ shortcutKey }}+Enter to generate</span>
                <span class="tabular-nums" :class="tooLong ? 'text-red-600' : 'text-stone-400'">
                    {{ characters.toLocaleString() }} / {{ options.maxPromptLength.toLocaleString() }}
                </span>
            </div>

            <div v-if="form.prompt.trim() === ''" class="mt-3 flex flex-wrap items-center gap-1.5">
                <span class="mr-0.5 text-xs text-stone-500">Try:</span>
                <button
                    v-for="idea in IDEAS.slice(0, 3)"
                    :key="idea.label"
                    type="button"
                    class="rounded-full border border-stone-200 px-2.5 py-1 text-xs text-stone-600 transition hover:border-stone-300 hover:bg-stone-50 hover:text-stone-900"
                    :title="idea.prompt"
                    @click="applyIdea(idea.prompt)"
                >
                    {{ idea.label }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium text-violet-700 transition hover:bg-violet-50 hover:text-violet-900"
                    @click="surpriseMe"
                >
                    <Icon name="shuffle" class="size-3.5" />
                    Surprise me
                </button>
            </div>
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-stone-700">Size</legend>
            <div class="mt-1.5 grid grid-cols-3 gap-2">
                <label
                    v-for="size in options.sizes"
                    :key="size.value"
                    class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border px-2 py-2.5 text-xs transition has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-violet-600"
                    :class="form.size === size.value ? 'border-violet-500 bg-violet-50 text-violet-800' : 'border-stone-200 text-stone-600 hover:border-stone-300'"
                >
                    <input v-model="form.size" type="radio" name="size" :value="size.value" class="sr-only" />
                    <span class="rounded-[3px] border-2 border-current" :class="ASPECTS[size.value]"></span>
                    <span class="font-medium">{{ size.label }}</span>
                    <span class="text-[11px] opacity-70">{{ size.value }}</span>
                </label>
            </div>
            <p v-if="form.errors.size" class="mt-1 text-xs text-red-600">{{ form.errors.size }}</p>
        </fieldset>

        <fieldset>
            <legend class="text-sm font-medium text-stone-700">Quality</legend>
            <div class="mt-1.5 grid grid-cols-3 gap-1 rounded-lg bg-stone-100 p-1">
                <label
                    v-for="quality in options.qualities"
                    :key="quality"
                    class="cursor-pointer rounded-md py-1.5 text-center text-sm capitalize transition has-focus-visible:outline-2 has-focus-visible:outline-violet-600"
                    :class="form.quality === quality ? 'bg-white font-medium text-stone-900 shadow-xs' : 'text-stone-500 hover:text-stone-800'"
                >
                    <input v-model="form.quality" type="radio" name="quality" :value="quality" class="sr-only" />
                    {{ quality }}
                </label>
            </div>
            <p v-if="form.errors.quality" class="mt-1 text-xs text-red-600">{{ form.errors.quality }}</p>
        </fieldset>

        <div>
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium text-stone-700">Reference images <span class="font-normal text-stone-400">(optional)</span></span>
                <span class="text-xs text-stone-400">{{ form.reference_images.length }} / {{ options.maxReferenceImages }}</span>
            </div>
            <p class="mt-0.5 text-xs text-stone-500">Image URLs that guide the style. Each one adds 765 input tokens.</p>

            <div v-if="form.reference_images.length" class="mt-2 space-y-2">
                <div v-for="(url, index) in form.reference_images" :key="index" class="flex gap-2">
                    <input
                        v-model="form.reference_images[index]"
                        type="url"
                        placeholder="https://example.com/style.png"
                        :aria-label="`Reference image ${index + 1} URL`"
                        class="block w-full rounded-lg border bg-white px-3 py-1.5 text-sm shadow-xs outline-none placeholder:text-stone-400 focus:ring-2 focus:ring-violet-500/30"
                        :class="form.errors[`reference_images.${index}`] ? 'border-red-400' : 'border-stone-300 focus:border-violet-500'"
                    />
                    <button
                        type="button"
                        class="grid w-8 shrink-0 place-items-center rounded-lg text-stone-400 hover:bg-stone-100 hover:text-stone-700"
                        aria-label="Remove reference image"
                        @click="removeReference(index)"
                    >
                        <Icon name="x" class="size-4" />
                    </button>
                </div>
            </div>

            <button
                v-if="form.reference_images.length < options.maxReferenceImages"
                type="button"
                class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-violet-700 hover:text-violet-900"
                @click="addReference"
            >
                <Icon name="plus" class="size-4" />
                Add reference image
            </button>
            <p v-for="message in referenceErrors" :key="message" class="mt-1 text-xs text-red-600">{{ message }}</p>
        </div>

        <CostEstimate :estimate="estimate" :error="estimateError" :available="available" :shortfall="shortfall" />

        <!-- On large screens the form scrolls inside the sticky column, so keep the button in view. -->
        <div class="-mx-5 -mb-5 space-y-3 border-t border-stone-100 bg-white px-5 pt-4 pb-5 lg:sticky lg:-bottom-5">
            <p v-if="form.errors.credits || form.errors.pricing" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200">
                {{ form.errors.credits ?? form.errors.pricing }}
            </p>

            <SubmitButton :processing="form.processing" processing-label="Queuing…" :disabled="!canSubmit">
                <span>Generate image<template v-if="estimate"> · up to <Usd :value="estimate.max_price" /></template></span>
            </SubmitButton>
        </div>
    </form>
</template>
