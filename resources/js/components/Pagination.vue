<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

defineProps({
    links: { type: Object, required: true },
    meta: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="meta.last_page > 1" aria-label="Pagination" class="flex items-center justify-between text-sm">
        <component
            :is="links.prev ? Link : 'span'"
            :href="links.prev ?? undefined"
            preserve-scroll
            class="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-3 py-1.5 font-medium text-stone-700 shadow-xs"
            :class="links.prev ? 'hover:border-stone-300 hover:text-stone-900' : 'opacity-40'"
        >
            <Icon name="arrow-left" class="size-4" />
            Newer
        </component>
        <span class="text-stone-500 tabular-nums">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <component
            :is="links.next ? Link : 'span'"
            :href="links.next ?? undefined"
            preserve-scroll
            class="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-3 py-1.5 font-medium text-stone-700 shadow-xs"
            :class="links.next ? 'hover:border-stone-300 hover:text-stone-900' : 'opacity-40'"
        >
            Older
            <Icon name="arrow-right" class="size-4" />
        </component>
    </nav>
</template>
