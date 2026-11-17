import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    pages: './pages',
    title: (title) => (title ? `${title} - ${appName}` : appName),
});
