<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CreditTransactionsTable from '../../../components/CreditTransactionsTable.vue';
import GenerationsTable from '../../../components/GenerationsTable.vue';
import Icon from '../../../components/Icon.vue';
import StatTile from '../../../components/StatTile.vue';
import SubmitButton from '../../../components/SubmitButton.vue';
import TextField from '../../../components/TextField.vue';
import Usd from '../../../components/Usd.vue';
import UserBadges from '../../../components/UserBadges.vue';
import { formatDate } from '../../../lib/time';

const props = defineProps({
    user: { type: Object, required: true },
    isCurrentUser: { type: Boolean, required: true },
    userCredits: { type: Object, required: true },
    stats: { type: Object, required: true },
    generations: { type: Array, required: true },
    transactions: { type: Object, required: true },
});

const requestsHint = computed(() => {
    const parts = [`${props.stats.succeeded.toLocaleString()} succeeded`, `${props.stats.failed.toLocaleString()} failed`];

    if (props.stats.in_flight > 0) {
        parts.push(`${props.stats.in_flight.toLocaleString()} processing`);
    }

    return parts.join(' · ');
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    is_active: props.user.is_active,
});

function save() {
    form.patch(`/admin/users/${props.user.id}`, {
        preserveScroll: true,
        // What was saved is the new baseline, so Save disables until the next change.
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <div class="space-y-8">
        <Head :title="user.name" />

        <div>
            <Link href="/admin/users" class="inline-flex items-center gap-1.5 text-sm text-stone-500 hover:text-stone-900">
                <Icon name="arrow-left" class="size-4" />
                Users
            </Link>
            <h1 class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-2xl font-semibold tracking-tight text-stone-900">
                {{ user.name }}
                <UserBadges :user="user" />
            </h1>
            <p class="mt-1 text-sm text-stone-500">
                {{ user.email }}
                <template v-if="!user.email_verified"> (unverified)</template>
                · Joined {{ formatDate(user.created_at) }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatTile label="Balance">
                <Usd :value="userCredits.balance" />
                <template #hint>
                    <Usd :value="userCredits.held" /> reserved for unfinished images
                </template>
            </StatTile>
            <StatTile label="Requests" :hint="requestsHint">
                {{ stats.generations.toLocaleString() }}
            </StatTile>
            <StatTile label="Spent on images" hint="All time, at the actual price">
                <Usd :value="stats.spent" />
            </StatTile>
        </div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-8">
                <section aria-labelledby="requests-heading" class="space-y-3">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="requests-heading" class="font-semibold">Recent requests</h2>
                        <Link
                            v-if="stats.generations > generations.length"
                            :href="`/admin/generations?user=${user.id}`"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-violet-700 hover:text-violet-900"
                        >
                            All {{ stats.generations.toLocaleString() }}
                            <Icon name="arrow-right" class="size-4" />
                        </Link>
                    </div>
                    <GenerationsTable :generations="generations" :show-user="false" empty-text="This user hasn't requested any images yet." />
                </section>

                <section aria-labelledby="history-heading" class="space-y-3">
                    <h2 id="history-heading" class="font-semibold">Credit history</h2>
                    <CreditTransactionsTable :transactions="transactions" empty-text="No purchases or charges yet." />
                </section>
            </div>

            <!-- First on small screens, so the account controls aren't below both tables. -->
            <form class="order-first rounded-2xl border border-stone-200 bg-white p-5 lg:sticky lg:top-24 lg:order-none lg:self-start" @submit.prevent="save">
                <h2 class="font-semibold">Account</h2>
                <p class="mt-1 text-sm text-stone-500">Changing the email marks it unverified.</p>

                <div class="mt-5 space-y-5">
                    <TextField v-model="form.name" label="Name" autocomplete="off" :error="form.errors.name" />
                    <TextField v-model="form.email" label="Email" type="email" autocomplete="off" :error="form.errors.email" />

                    <div>
                        <label class="flex items-start gap-3" :class="isCurrentUser ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'">
                            <input v-model="form.is_active" type="checkbox" :disabled="isCurrentUser" class="mt-0.5 size-4 accent-violet-600" />
                            <span>
                                <span class="block text-sm font-medium text-stone-700">Active</span>
                                <span class="block text-sm text-stone-500">
                                    {{ isCurrentUser ? "You can't deactivate your own account." : "Inactive users can't sign in, and are signed out everywhere." }}
                                </span>
                            </span>
                        </label>
                        <p v-if="form.errors.is_active" class="mt-1 text-sm text-red-600">{{ form.errors.is_active }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3">
                    <p v-if="form.recentlySuccessful" role="status" class="text-center text-sm text-emerald-700">Saved.</p>
                    <SubmitButton :processing="form.processing" processing-label="Saving…" :disabled="!form.isDirty">Save</SubmitButton>
                </div>
            </form>
        </div>
    </div>
</template>
