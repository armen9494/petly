<script setup>
import Icon from './Icon.vue';

/**
 * A dropdown that narrows a list. The empty value means "no filter" and is
 * shown as allLabel, such as "All statuses". The native arrow is swapped for
 * the app's chevron so it sits inset like the other fields' content.
 */
defineProps({
    label: { type: String, required: true },
    allLabel: { type: String, required: true },
    options: { type: Array, required: true },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <label class="relative block">
        <span class="sr-only">{{ label }}</span>
        <select
            v-model="model"
            class="block w-full cursor-pointer appearance-none rounded-lg border border-stone-300 bg-white py-2 pr-9 pl-3 text-sm shadow-xs outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/30"
        >
            <option value="">{{ allLabel }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-stone-400" />
    </label>
</template>
