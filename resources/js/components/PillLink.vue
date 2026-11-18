<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

/**
 * A pill-shaped header link with an icon, shaped to sit beside the credits
 * pill. With `iconOnlyOnMobile`, small screens show just the icon; the label
 * stays for screen readers and as the hover title.
 */
defineProps({
    href: { type: String, required: true },
    icon: { type: String, required: true },
    label: { type: String, required: true },
    iconOnlyOnMobile: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="href"
        :title="iconOnlyOnMobile ? label : undefined"
        class="flex shrink-0 items-center gap-1.5 rounded-full border border-stone-200 bg-white text-sm font-medium whitespace-nowrap text-stone-600 shadow-xs hover:border-stone-300 hover:text-stone-900"
        :class="iconOnlyOnMobile ? 'p-1.5 sm:px-3 sm:py-1' : 'px-3 py-1'"
    >
        <Icon :name="icon" class="size-4 text-stone-400" />
        <span :class="{ 'sr-only sm:not-sr-only': iconOnlyOnMobile }">{{ label }}</span>
    </Link>
</template>
