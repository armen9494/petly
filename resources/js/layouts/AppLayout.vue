<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandMark from '../components/BrandMark.vue';
import FlashMessage from '../components/FlashMessage.vue';
import PillLink from '../components/PillLink.vue';
import UserMenu from '../components/UserMenu.vue';
import Usd from '../components/Usd.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const credits = computed(() => page.props.credits);

// The admin panel isn't part of the app's navigation: admins reach it from its own header button and the account menu.
const LINKS = [
    { href: '/', label: 'Studio', component: 'web/Studio' },
    { href: '/credits', label: 'Credits', component: 'web/Credits' },
];
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900">
        <header class="sticky top-0 z-20 border-b border-stone-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 sm:flex-nowrap sm:px-6">
                <Link href="/" aria-label="Petly studio"><BrandMark /></Link>

                <nav aria-label="Main" class="order-last flex w-full gap-1 overflow-x-auto text-sm [scrollbar-width:none] sm:order-none sm:w-auto">
                    <Link
                        v-for="link in LINKS"
                        :key="link.href"
                        :href="link.href"
                        :aria-current="page.component === link.component ? 'page' : undefined"
                        class="shrink-0 rounded-md px-3 py-1.5 font-medium transition"
                        :class="page.component === link.component ? 'bg-stone-100 text-stone-900' : 'text-stone-500 hover:text-stone-900'"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-3 text-sm">
                    <PillLink v-if="user?.is_admin" href="/admin" icon="shield" label="Admin panel" icon-only-on-mobile />
                    <Link
                        v-if="credits"
                        href="/credits"
                        class="flex items-center gap-2 rounded-full border border-stone-200 bg-white py-1 pr-1 pl-3 shadow-xs hover:border-stone-300"
                        title="Credits you can spend now"
                    >
                        <span class="hidden text-stone-500 sm:inline">Available</span>
                        <Usd :value="credits.available" class="font-semibold" />
                        <span class="rounded-full bg-violet-600 px-2.5 py-0.5 text-xs font-medium text-white">Buy</span>
                    </Link>
                    <UserMenu v-if="user" :user="user" />
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
            <slot />
        </main>

        <FlashMessage />
    </div>
</template>
