<script setup>
/**
 * A small set of stroke icons drawn on a 24px grid. Size and color come from
 * the caller (`class="size-4"`, `text-*`), so the icon follows its text.
 */
defineProps({
    name: { type: String, required: true },
});

const PATHS = {
    alert: 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18z M12 8v5 M12 16.5h.01',
    'arrow-left': 'M19 12H5 M11 6l-6 6 6 6',
    'arrow-right': 'M5 12h14 M13 6l6 6-6 6',
    chart: 'M4 4v16h16 M8.5 16v-4 M12.5 16V8 M16.5 16v-6',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    'chevron-down': 'M6 9l6 6 6-6',
    clock: 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18z M12 7.5V12l3 2',
    copy: 'M8 8h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2z M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2',
    external: 'M14 4h6v6 M20 4l-9 9 M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4',
    grid: 'M5 4h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z M15 4h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z M5 14h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z M15 14h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z',
    home: 'M4 10.5L12 4l8 6.5V19a1 1 0 0 1-1 1h-4.5v-5.5h-5V20H5a1 1 0 0 1-1-1z',
    image: 'M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z M4 16l5-5 4 4 2-2 5 5 M15.5 8.5h.01',
    logout: 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3 M10 16l4-4-4-4 M14 12H4',
    plus: 'M12 5v14 M5 12h14',
    receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6 M9 12h6 M9 16h3',
    shield: 'M12 3.5l7 2.8v5.2c0 4.3-2.9 7.9-7 9.5c-4.1-1.6-7-5.2-7-9.5V6.3z M9 12l2.2 2.2L15.5 10',
    shuffle: 'M16 4h4v4 M4 20L20 4 M16 20h4v-4 M15 15l5 5 M4 4l5 5',
    user: 'M12 4a4 4 0 1 0 0 8a4 4 0 1 0 0-8z M4.5 20c1.2-3.6 4.1-5.5 7.5-5.5s6.3 1.9 7.5 5.5',
    users: 'M9 4.5a3.5 3.5 0 1 0 0 7a3.5 3.5 0 1 0 0-7z M2.5 20c.9-3.2 3.3-5 6.5-5s5.6 1.8 6.5 5 M15.5 4.8a3.5 3.5 0 0 1 0 6.4 M17.5 15.3c2 .7 3.3 2.3 4 4.7',
    wallet: 'M4 7a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v2 M4 7v11a2 2 0 0 0 2 2h13a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1H6a2 2 0 0 1-2-1z M16 14h.01',
    x: 'M6 6l12 12 M18 6L6 18',
};
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path :d="PATHS[name]" />
    </svg>
</template>
