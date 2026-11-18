<script setup>
/**
 * A search box for a list. The label doubles as the placeholder.
 */
defineProps({
    label: { type: String, required: true },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <label class="block">
        <span class="sr-only">{{ label }}</span>
        <input
            v-model="model"
            type="search"
            :placeholder="label"
            class="block w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm shadow-xs outline-none placeholder:text-stone-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/30"
        />
    </label>
</template>
