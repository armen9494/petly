<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    type: { type: String, default: 'text' },
    autocomplete: { type: String, default: null },
    placeholder: { type: String, default: null },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <label class="block">
        <span class="text-sm font-medium text-stone-700">{{ label }}</span>
        <input
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :aria-invalid="error ? 'true' : undefined"
            class="mt-1.5 block w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-xs outline-none placeholder:text-stone-400 focus:ring-2 focus:ring-violet-500/30"
            :class="error ? 'border-red-400 focus:border-red-500' : 'border-stone-300 focus:border-violet-500'"
        />
        <span v-if="error" class="mt-1 block text-sm text-red-600">{{ error }}</span>
    </label>
</template>
