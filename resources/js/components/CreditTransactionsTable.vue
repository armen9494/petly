<script setup>
import { formatDateTime } from '../lib/time';
import Icon from './Icon.vue';
import Pagination from './Pagination.vue';
import Usd from './Usd.vue';

/**
 * A paginated credit history: purchases and image charges.
 */
defineProps({
    transactions: { type: Object, required: true },
    emptyText: { type: String, required: true },
});
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-xs">
            <table class="w-full text-sm">
                <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="transaction in transactions.data" :key="transaction.id">
                        <td class="px-4 py-3 whitespace-nowrap text-stone-500">{{ formatDateTime(transaction.created_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-2">
                                <Icon
                                    :name="transaction.type === 'purchase' ? 'plus' : 'image'"
                                    class="size-4 shrink-0"
                                    :class="transaction.type === 'purchase' ? 'text-emerald-600' : 'text-stone-400'"
                                />
                                {{ transaction.description }}
                            </span>
                        </td>
                        <td
                            class="px-4 py-3 text-right font-medium whitespace-nowrap"
                            :class="transaction.type === 'purchase' ? 'text-emerald-700' : 'text-stone-900'"
                        >
                            <Usd :value="transaction.amount" signed />
                        </td>
                    </tr>
                    <tr v-if="transactions.data.length === 0">
                        <td colspan="3" class="px-4 py-10 text-center text-stone-500">{{ emptyText }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="transactions.links" :meta="transactions.meta" />
    </div>
</template>
