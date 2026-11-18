<script setup>
import { computed, ref, watch } from 'vue';
import { formatDateTime, timeAgo } from '../lib/time';
import GenerationLightbox from './GenerationLightbox.vue';
import Icon from './Icon.vue';
import StatusBadge from './StatusBadge.vue';
import Usd from './Usd.vue';

const props = defineProps({
    generation: { type: Object, required: true },
});

const g = computed(() => props.generation);
const lightbox = ref(null);

// The image may still fail to load, say if Pixelforge's link lapses before
// we've saved our own copy. Try again whenever the link changes.
const imageFailed = ref(false);

watch(
    () => g.value.image_url,
    () => (imageFailed.value = false),
);

const showsImage = computed(() => Boolean(g.value.image_url) && !imageFailed.value);

const waitingText = computed(() => {
    if (g.value.status_detail) {
        return g.value.status_detail;
    }

    return {
        pending: 'Sending to Pixelforge…',
        queued: 'Waiting in Pixelforge’s queue…',
        processing: 'Generating your image…',
    }[g.value.status];
});

const referenceCount = computed(() => g.value.reference_images.length);
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs transition hover:shadow-md">
        <div class="relative aspect-square bg-stone-100">
            <button v-if="showsImage" type="button" class="block size-full cursor-zoom-in" :aria-label="`View image: ${g.prompt}`" @click="lightbox?.open()">
                <img :src="g.image_url" :alt="g.prompt" loading="lazy" class="size-full object-contain" @error="imageFailed = true" />
            </button>

            <div v-else class="grid size-full place-items-center p-6 text-center">
                <div v-if="g.status === 'failed'">
                    <span class="mx-auto grid size-10 place-items-center rounded-full bg-red-100 text-red-600">
                        <Icon name="alert" class="size-5" />
                    </span>
                    <p class="mt-3 text-sm font-medium text-stone-800">This image failed</p>
                    <p class="mt-1 text-sm text-stone-500">{{ g.error_message ?? 'Something went wrong.' }}</p>
                </div>
                <div v-else-if="g.image_expired">
                    <span class="mx-auto grid size-10 place-items-center rounded-full bg-stone-200/70 text-stone-500">
                        <Icon name="clock" class="size-5" />
                    </span>
                    <p class="mt-3 text-sm font-medium text-stone-700">Image link expired</p>
                    <p class="mt-1 text-sm text-stone-500">Pixelforge’s image links last 24 hours.</p>
                </div>
                <div v-else-if="imageFailed">
                    <span class="mx-auto grid size-10 place-items-center rounded-full bg-stone-200/70 text-stone-500">
                        <Icon name="image" class="size-5" />
                    </span>
                    <p class="mt-3 text-sm font-medium text-stone-700">Image unavailable</p>
                    <p class="mt-1 text-sm text-stone-500">It couldn’t be loaded just now.</p>
                </div>
                <div v-else>
                    <!-- Spin through every unfinished status: swapping the animation
                         on a status change would freeze or restart the ring. -->
                    <div aria-hidden="true" class="mx-auto size-10 animate-spin rounded-full border-[3px] border-violet-200 border-t-violet-600"></div>
                    <p class="mt-3 text-sm text-stone-600">{{ waitingText }}</p>
                </div>
            </div>

            <StatusBadge :status="g.status" class="absolute top-3 left-3" />
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4 text-sm">
            <p class="line-clamp-2 text-stone-800" :title="g.prompt">{{ g.prompt }}</p>
            <p class="text-xs text-stone-500">
                {{ g.size_label }} {{ g.size }} · {{ g.quality }} quality
                <template v-if="referenceCount"> · {{ referenceCount }} reference{{ referenceCount === 1 ? '' : 's' }}</template>
                · <time :datetime="g.created_at" :title="formatDateTime(g.created_at)">{{ timeAgo(g.created_at) }}</time>
            </p>

            <dl class="mt-auto space-y-1 border-t border-stone-100 pt-3">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-stone-500">Estimate</dt>
                    <dd class="whitespace-nowrap text-stone-700"><Usd :value="g.estimated_min_price" /> – <Usd :value="g.estimated_max_price" /></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-stone-500">Actual cost</dt>
                    <dd v-if="g.status === 'succeeded'" class="font-semibold text-stone-900"><Usd :value="g.price" /></dd>
                    <dd v-else-if="g.status === 'failed'" class="font-medium text-emerald-700">Free, not charged</dd>
                    <dd v-else class="text-stone-500">When it finishes</dd>
                </div>
            </dl>
        </div>

        <GenerationLightbox v-if="showsImage" ref="lightbox" :generation="g" />
    </article>
</template>
