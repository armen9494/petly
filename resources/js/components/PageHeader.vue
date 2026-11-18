<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-2xl text-sm text-stone-500">{{ description }}</p>
        </div>
        <slot />
    </div>
</template>
