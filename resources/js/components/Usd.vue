<script setup>
import { computed } from 'vue';
import { formatUsd } from '../lib/money';

const props = defineProps({
    value: { type: String, default: null },
    digits: { type: Number, default: 4 },
    signed: { type: Boolean, default: false },
});

const formatted = computed(() => {
    const text = formatUsd(props.value, props.digits);

    return props.signed && props.value && !props.value.startsWith('-') ? `+${text}` : text;
});
</script>

<template>
    <span class="tabular-nums" :title="value === null ? undefined : `Exactly $${value}`">{{ formatted }}</span>
</template>
