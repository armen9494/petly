<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandMark from '../components/BrandMark.vue';
import FlashMessage from '../components/FlashMessage.vue';
import Icon from '../components/Icon.vue';
import PillLink from '../components/PillLink.vue';
import UserMenu from '../components/UserMenu.vue';

/**
 * The admin panel's own shell, kept apart from the app's: a sidebar of
 * sections on large screens, and a scrolling row of sections under the top
 * bar on small ones. The top bar always holds the way back to the user
 * dashboard and the same account menu as the app's header. A section is
 * current when the page component starts with its prefix.
 */
const SECTIONS = [
    { href: '/admin', label: 'Overview', icon: 'grid', prefix: 'admin/Dashboard' },
    { href: '/admin/users', label: 'Users', icon: 'users', prefix: 'admin/users/' },
    { href: '/admin/generations', label: 'Generations', icon: 'image', prefix: 'admin/Generations' },
    { href: '/admin/report', label: 'Spend report', icon: 'chart', prefix: 'admin/Report' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

const isCurrent = (section) => page.component.startsWith(section.prefix);
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900 lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="sticky top-0 hidden h-screen flex-col border-r border-stone-200 bg-white lg:flex">
            <Link href="/admin" class="flex items-center gap-2 px-5 py-4" aria-label="Petly admin overview">
                <BrandMark />
                <span class="rounded bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700">Admin</span>
            </Link>

            <nav aria-label="Admin" class="flex-1 space-y-0.5 px-3 py-2 text-sm">
                <Link
                    v-for="section in SECTIONS"
                    :key="section.href"
                    :href="section.href"
                    :aria-current="isCurrent(section) ? 'page' : undefined"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 font-medium transition"
                    :class="isCurrent(section) ? 'bg-stone-100 text-stone-900' : 'text-stone-500 hover:bg-stone-50 hover:text-stone-900'"
                >
                    <Icon :name="section.icon" class="size-4" :class="isCurrent(section) ? 'text-violet-600' : 'text-stone-400'" />
                    {{ section.label }}
                </Link>
            </nav>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-stone-200 bg-white">
                <div class="mx-auto flex w-full max-w-6xl items-center gap-4 px-4 py-3 sm:px-6 lg:h-15 lg:px-10">
                    <Link href="/admin" class="flex items-center gap-2 lg:hidden" aria-label="Petly admin overview">
                        <BrandMark />
                        <span class="rounded bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700">Admin</span>
                    </Link>
                    <div class="ml-auto flex items-center gap-3">
                        <PillLink href="/" icon="home" label="User dashboard" icon-only-on-mobile />
                        <UserMenu :user="user" />
                    </div>
                </div>
                <nav aria-label="Admin" class="flex gap-1 overflow-x-auto px-4 pb-2 text-sm [scrollbar-width:none] sm:px-6 lg:hidden">
                    <Link
                        v-for="section in SECTIONS"
                        :key="section.href"
                        :href="section.href"
                        :aria-current="isCurrent(section) ? 'page' : undefined"
                        class="shrink-0 rounded-md px-3 py-1.5 font-medium transition"
                        :class="isCurrent(section) ? 'bg-stone-100 text-stone-900' : 'text-stone-500 hover:text-stone-900'"
                    >
                        {{ section.label }}
                    </Link>
                </nav>
            </header>

            <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
                <slot />
            </main>
        </div>

        <FlashMessage />
    </div>
</template>
