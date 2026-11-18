<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import FilterSelect from '../../../components/FilterSelect.vue';
import PageHeader from '../../../components/PageHeader.vue';
import Pagination from '../../../components/Pagination.vue';
import SearchField from '../../../components/SearchField.vue';
import Usd from '../../../components/Usd.vue';
import UserBadges from '../../../components/UserBadges.vue';
import { applyFilters, useDebounced } from '../../../lib/filters';
import { formatDate } from '../../../lib/time';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const ROLES = [
    { value: 'admin', label: 'Admins' },
    { value: 'user', label: 'Users' },
];

const STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? '');

function apply() {
    applyFilters('/admin/users', { search: search.value, role: role.value, status: status.value });
}

watch([role, status], apply);
watch(search, useDebounced(apply));

const HEAD_CELL = 'px-4 py-3 font-medium';
const CELL = 'px-4 py-3';
</script>

<template>
    <div class="space-y-6">
        <Head title="Users" />

        <PageHeader title="Users" :description="`${users.meta.total.toLocaleString()} matching account${users.meta.total === 1 ? '' : 's'}, newest first.`" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <FilterSelect v-model="role" label="Role" all-label="All roles" :options="ROLES" class="sm:w-40" />
            <FilterSelect v-model="status" label="Status" all-label="All statuses" :options="STATUSES" class="sm:w-40" />
            <SearchField v-model="search" label="Search by name or email" class="sm:w-72" />
        </div>

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-xs">
            <table class="w-full text-sm">
                <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                    <tr>
                        <th :class="HEAD_CELL">User</th>
                        <th :class="HEAD_CELL">Joined</th>
                        <th :class="HEAD_CELL" class="text-right">Requests</th>
                        <th :class="HEAD_CELL" class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-stone-50">
                        <td :class="CELL">
                            <Link :href="`/admin/users/${user.id}`" class="flex items-center gap-2 font-medium text-stone-900 hover:underline">
                                {{ user.name }}
                                <UserBadges :user="user" />
                            </Link>
                            <span class="block text-xs text-stone-500">{{ user.email }}</span>
                        </td>
                        <td :class="CELL" class="whitespace-nowrap text-stone-500">{{ formatDate(user.created_at) }}</td>
                        <td :class="CELL" class="text-right tabular-nums">{{ user.generations_count.toLocaleString() }}</td>
                        <td :class="CELL" class="text-right font-medium whitespace-nowrap"><Usd :value="user.balance" /></td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-stone-500">No users match.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="users.links" :meta="users.meta" />
    </div>
</template>
