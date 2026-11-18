const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const UNITS = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/**
 * "just now", "3 minutes ago", "yesterday", ...
 */
export function timeAgo(iso) {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export function formatDate(iso) {
    return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
}

export function formatDateTime(iso) {
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
