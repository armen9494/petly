<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import GenerationFailure from '../../components/GenerationFailure.vue';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import StatTile from '../../components/StatTile.vue';
import Usd from '../../components/Usd.vue';
import UserBadges from '../../components/UserBadges.vue';
import { formatDateTime, timeAgo } from '../../lib/time';

const props = defineProps({
    stats: { type: Object, required: true },
    stuckAfterMinutes: { type: Number, required: true },
    newestUsers: { type: Array, required: true },
    latestFailures: { type: Array, required: true },
});

const generations = computed(() => props.stats.generations);
const finished = computed(() => generations.value.succeeded + generations.value.failed);

const plural = (count, word) => `${count.toLocaleString()} ${word}${count === 1 ? '' : 's'}`;

const CARD = 'rounded-xl border border-stone-200 bg-white shadow-xs';
const CARD_FOOTER = 'flex items-center gap-1.5 border-t border-stone-100 px-5 py-3 text-sm font-medium text-violet-700 hover:text-violet-900';
</script>

<template>
    <div class="space-y-8">
        <Head title="Admin" />

        <PageHeader title="Overview" :description="`The last 30 UTC days, ${stats.from} to ${stats.to}, and what's running now.`" />

        <div v-if="generations.stuck > 0" role="status" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <Icon name="alert" class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <p>
                {{ plural(generations.stuck, 'request') }} {{ generations.stuck === 1 ? 'has' : 'have' }} been in progress for more than
                {{ stuckAfterMinutes }} minutes. Pixelforge's webhook and the every-minute sync may both have missed
                {{ generations.stuck === 1 ? 'it' : 'them' }}.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile label="Users">
                {{ stats.users.total.toLocaleString() }}
                <template #hint>
                    +{{ stats.users.new.toLocaleString() }} in 30 days
                    <template v-if="stats.users.inactive"> · {{ stats.users.inactive.toLocaleString() }} inactive</template>
                </template>
            </StatTile>
            <StatTile label="Finished requests (30 days)">
                {{ finished.toLocaleString() }}
                <template #hint>
                    {{ generations.succeeded.toLocaleString() }} succeeded · {{ generations.failed.toLocaleString() }} failed ·
                    {{ generations.in_flight.toLocaleString() }} in progress now
                </template>
            </StatTile>
            <StatTile label="Credit spend (30 days)" tone="emerald">
                <Usd :value="stats.spend.charged" />
                <template #hint>
                    <Usd :value="stats.spend.profit" /> profit ·
                    <Link href="/admin/report" class="font-medium underline-offset-2 hover:underline">Spend report</Link>
                </template>
            </StatTile>
            <StatTile label="Unspent credits" hint="Credits users have bought but not used yet">
                <Usd :value="stats.unspent_credits" />
            </StatTile>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="newest-users-heading" :class="CARD">
                <h2 id="newest-users-heading" class="px-5 pt-4 pb-2 font-semibold">Newest users</h2>
                <ul class="divide-y divide-stone-100">
                    <li v-for="user in newestUsers" :key="user.id">
                        <Link :href="`/admin/users/${user.id}`" class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-stone-50">
                            <span class="min-w-0">
                                <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                                    <span class="truncate">{{ user.name }}</span>
                                    <UserBadges :user="user" />
                                </span>
                                <span class="block truncate text-xs text-stone-500">{{ user.email }}</span>
                            </span>
                            <time :datetime="user.created_at" :title="formatDateTime(user.created_at)" class="shrink-0 text-xs text-stone-500">
                                {{ timeAgo(user.created_at) }}
                            </time>
                        </Link>
                    </li>
                    <li v-if="newestUsers.length === 0" class="px-5 py-8 text-center text-sm text-stone-500">No users yet.</li>
                </ul>
                <Link href="/admin/users" :class="CARD_FOOTER">
                    All users
                    <Icon name="arrow-right" class="size-4" />
                </Link>
            </section>

            <section aria-labelledby="latest-failures-heading" :class="CARD">
                <h2 id="latest-failures-heading" class="px-5 pt-4 pb-2 font-semibold">Latest failed requests</h2>
                <ul class="divide-y divide-stone-100">
                    <li v-for="generation in latestFailures" :key="generation.id" class="px-5 py-3 text-sm">
                        <div class="flex items-baseline justify-between gap-4">
                            <p class="truncate text-stone-800" :title="generation.prompt">{{ generation.prompt }}</p>
                            <time :datetime="generation.created_at" :title="formatDateTime(generation.created_at)" class="shrink-0 text-xs text-stone-500">
                                {{ timeAgo(generation.created_at) }}
                            </time>
                        </div>
                        <GenerationFailure :generation="generation" truncate class="mt-0.5" />
                        <Link :href="`/admin/users/${generation.user.id}`" class="mt-0.5 block truncate text-xs text-stone-500 hover:text-stone-900">
                            {{ generation.user.email }}
                        </Link>
                    </li>
                    <li v-if="latestFailures.length === 0" class="px-5 py-8 text-center text-sm text-stone-500">No failed requests.</li>
                </ul>
                <Link href="/admin/generations?status=failed" :class="CARD_FOOTER">
                    All failed requests
                    <Icon name="arrow-right" class="size-4" />
                </Link>
            </section>
        </div>
    </div>
</template>
