import { onBeforeUnmount, ref } from 'vue';

/**
 * Copy text to the clipboard, with a `copied` flag that stays true for a moment
 * afterwards so the button can confirm it.
 */
export function useClipboard(resetAfterMs = 1600) {
    const copied = ref(false);
    let timer = null;

    async function copy(text) {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            return;
        }

        copied.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (copied.value = false), resetAfterMs);
    }

    onBeforeUnmount(() => clearTimeout(timer));

    return { copied, copy };
}
