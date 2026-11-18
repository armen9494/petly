<script setup>
defineProps({
    mobile: { type: Boolean, default: false },
});
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="mobile">
            <rect x="7" y="2.5" width="10" height="19" rx="2" />
            <path d="M11 18.5h2" />
        </template>
        <template v-else>
            <rect x="2.5" y="4" width="19" height="12.5" rx="1.5" />
            <path d="M9 20.5h6M12 16.5v4" />
        </template>
    </svg>
</template>
