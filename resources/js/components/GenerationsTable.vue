<script setup>
import { Link } from '@inertiajs/vue3';
import { formatDateTime, timeAgo } from '../lib/time';
import GenerationFailure from './GenerationFailure.vue';
import StatusBadge from './StatusBadge.vue';
import Usd from './Usd.vue';

/**
 * Generation requests for admins, each with its expected price (the estimate
 * range when it was requested) next to its actual price (what was charged).
 */
defineProps({
    generations: { type: Array, required: true },
    showUser: { type: Boolean, default: true },
    emptyText: { type: String, default: 'No requests yet.' },
});

const HEAD_CELL = 'px-4 py-3 font-medium';
const CELL = 'px-4 py-3 align-top';
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-xs">
        <table class="w-full text-sm">
            <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                <tr>
                    <th :class="HEAD_CELL">Requested</th>
                    <th v-if="showUser" :class="HEAD_CELL">User</th>
                    <th :class="HEAD_CELL">Request</th>
                    <th :class="HEAD_CELL">Status</th>
                    <th :class="HEAD_CELL" class="text-right whitespace-nowrap">Expected price</th>
                    <th :class="HEAD_CELL" class="text-right whitespace-nowrap">Actual price</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <tr v-for="generation in generations" :key="generation.id">
                    <td :class="CELL" class="whitespace-nowrap text-stone-500">
                        <time :datetime="generation.created_at" :title="formatDateTime(generation.created_at)">{{ timeAgo(generation.created_at) }}</time>
                    </td>
                    <td v-if="showUser" :class="CELL">
                        <Link v-if="generation.user" :href="`/admin/users/${generation.user.id}`" class="font-medium text-stone-900 hover:underline">
                            {{ generation.user.name }}
                        </Link>
                        <span class="block text-xs text-stone-500">{{ generation.user?.email }}</span>
                    </td>
                    <td :class="CELL" class="max-w-md min-w-64">
                        <p class="line-clamp-2 text-stone-800" :title="generation.prompt">{{ generation.prompt }}</p>
                        <p class="mt-1 text-xs text-stone-500">
                            {{ generation.size_label }} {{ generation.size }} · {{ generation.quality }}
                            <template v-if="generation.reference_images_count">
                                · {{ generation.reference_images_count }} reference{{ generation.reference_images_count === 1 ? '' : 's' }}
                            </template>
                        </p>
                        <GenerationFailure v-if="generation.status === 'failed'" :generation="generation" class="mt-1" />
                    </td>
                    <td :class="CELL"><StatusBadge :status="generation.status" distinguish-pending /></td>
                    <td :class="CELL" class="text-right whitespace-nowrap text-stone-600">
                        <Usd :value="generation.estimated_min_price" /> – <Usd :value="generation.estimated_max_price" />
                    </td>
                    <td :class="CELL" class="text-right whitespace-nowrap">
                        <Usd v-if="generation.price !== null" :value="generation.price" class="font-medium text-stone-900" />
                        <span v-else-if="generation.status === 'failed'" class="text-stone-500">Not charged</span>
                        <span v-else class="text-stone-400">Pending</span>
                    </td>
                </tr>
                <tr v-if="generations.length === 0">
                    <td :colspan="showUser ? 6 : 5" class="px-4 py-10 text-center text-stone-500">{{ emptyText }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
