import { createInertiaApp } from '@inertiajs/vue3';
import AdminLayout from './layouts/AdminLayout.vue';
import AppLayout from './layouts/AppLayout.vue';
import GuestLayout from './layouts/GuestLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function layoutFor(name) {
    if (name.startsWith('auth/')) {
        return GuestLayout;
    }

    // The admin panel has its own shell rather than sitting inside the app's.
    return name.startsWith('admin/') ? AdminLayout : AppLayout;
}

createInertiaApp({
    pages: './pages',
    layout: layoutFor,
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: { color: '#7c3aed' },
});
