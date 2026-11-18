import { router } from '@inertiajs/vue3';
import { onBeforeUnmount } from 'vue';

/**
 * Reload a filtered list with new filters. Empty filters are left out of the
 * URL, and the page number is dropped so the results start from the top.
 * Local state (such as a half-typed search) survives the reload.
 */
export function applyFilters(url, filters) {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== ''));

    router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
}

/**
 * Call `callback` once typing has paused for `delayMs`, so a search box
 * doesn't send a request for every keystroke.
 */
export function useDebounced(callback, delayMs = 300) {
    let timer = null;

    onBeforeUnmount(() => clearTimeout(timer));

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), delayMs);
    };
}
