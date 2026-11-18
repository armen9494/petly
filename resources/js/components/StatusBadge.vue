<script setup>
import { computed } from 'vue';

/**
 * Users see "pending" (saved, not yet accepted by Pixelforge) as "Queued", since
 * to them it's the same wait. Admins need to tell the two apart.
 */
const props = defineProps({
    status: { type: String, required: true },
    distinguishPending: { type: Boolean, default: false },
});

const STATUSES = {
    pending: { label: props.distinguishPending ? 'Pending' : 'Queued', classes: 'bg-white/90 text-stone-700 ring-stone-200', live: true },
    queued: { label: 'Queued', classes: 'bg-white/90 text-stone-700 ring-stone-200', live: true },
    processing: { label: 'Processing', classes: 'bg-violet-50/95 text-violet-700 ring-violet-200', live: true },
    succeeded: { label: 'Succeeded', classes: 'bg-emerald-50/95 text-emerald-700 ring-emerald-200', live: false },
    failed: { label: 'Failed', classes: 'bg-red-50/95 text-red-700 ring-red-200', live: false },
};

const badge = computed(() => STATUSES[props.status] ?? STATUSES.queued);
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium shadow-xs ring-1" :class="badge.classes">
        <span class="relative flex size-1.5">
            <span v-if="badge.live" class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-60"></span>
            <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
        </span>
        {{ badge.label }}
    </span>
</template>
