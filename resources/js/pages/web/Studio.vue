<script setup>
import { Head, usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import GenerationCard from '../../components/GenerationCard.vue';
import GenerationForm from '../../components/GenerationForm.vue';
import Icon from '../../components/Icon.vue';
import Pagination from '../../components/Pagination.vue';

const props = defineProps({
    generations: { type: Object, required: true },
    hasGenerationsInFlight: { type: Boolean, required: true },
    options: { type: Object, required: true },
});

// Refresh statuses (and the credit balance) every two seconds while any
// generation is unfinished; stop once they all are.
const { start, stop, polling } = usePoll(
    2000,
    { only: ['generations', 'hasGenerationsInFlight', 'credits'] },
    { autoStart: false },
);

watch(
    () => props.hasGenerationsInFlight,
    (inFlight) => (inFlight ? start() : stop()),
    { immediate: true },
);
</script>

<template>
    <div class="grid gap-8 lg:grid-cols-[380px_minmax(0,1fr)]">
        <Head title="Studio" />

        <section aria-labelledby="create-heading" class="lg:sticky lg:top-24 lg:self-start">
            <h1 id="create-heading" class="mb-4 text-lg font-semibold">Create an image</h1>
            <GenerationForm :options="options" />
        </section>

        <section aria-labelledby="gallery-heading" class="space-y-5">
            <div class="flex items-baseline justify-between gap-4">
                <h2 id="gallery-heading" class="text-lg font-semibold">Your images</h2>
                <span class="flex items-center gap-3 text-sm text-stone-500">
                    <span v-if="polling" class="flex items-center gap-1.5 text-violet-700">
                        <span class="size-1.5 animate-pulse rounded-full bg-violet-600"></span>
                        Updating live
                    </span>
                    <span class="tabular-nums">{{ generations.meta.total.toLocaleString() }} total</span>
                </span>
            </div>

            <div
                v-if="generations.data.length === 0"
                class="grid place-items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-20 text-center"
            >
                <div>
                    <span class="mx-auto grid size-11 place-items-center rounded-full bg-violet-50 text-violet-600">
                        <Icon name="image" class="size-5" />
                    </span>
                    <p class="mt-4 font-medium text-stone-800">No images yet</p>
                    <p class="mt-1 text-sm text-stone-500">Write a prompt, or try one of the ideas, to create your first image.</p>
                </div>
            </div>

            <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <GenerationCard v-for="generation in generations.data" :key="generation.id" :generation="generation" />
            </div>

            <Pagination :links="generations.links" :meta="generations.meta" />
        </section>
    </div>
</template>
