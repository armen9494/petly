<script setup>
import { computed } from 'vue';

/**
 * One headline number: a label, the value (default slot) and an optional hint
 * line (the hint prop, or the hint slot for richer content such as links).
 */
const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: 'neutral', validator: (tone) => ['neutral', 'violet', 'emerald'].includes(tone) },
});

const TONES = {
    neutral: { box: 'border-stone-200 bg-white', label: 'text-stone-500', value: 'text-stone-900', hint: 'text-stone-500' },
    violet: { box: 'border-violet-200 bg-violet-50', label: 'text-violet-700', value: 'text-violet-900', hint: 'text-violet-700' },
    emerald: { box: 'border-emerald-200 bg-emerald-50', label: 'text-emerald-700', value: 'text-emerald-900', hint: 'text-emerald-700' },
};

const classes = computed(() => TONES[props.tone]);
</script>

<template>
    <div class="rounded-xl border p-5 shadow-xs" :class="classes.box">
        <p class="text-sm" :class="classes.label">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums" :class="classes.value"><slot /></p>
        <p v-if="hint || $slots.hint" class="mt-1 text-xs" :class="classes.hint">
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>
