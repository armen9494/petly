<script setup>
defineProps({
    processing: { type: Boolean, default: false },
    processingLabel: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button
        type="submit"
        :disabled="processing || disabled"
        :aria-busy="processing"
        class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-violet-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-600 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <template v-if="processing">
            <span aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
            {{ processingLabel }}
        </template>
        <slot v-else />
    </button>
</template>
