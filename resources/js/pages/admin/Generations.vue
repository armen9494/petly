<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FilterSelect from '../../components/FilterSelect.vue';
import GenerationsTable from '../../components/GenerationsTable.vue';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import Pagination from '../../components/Pagination.vue';
import SearchField from '../../components/SearchField.vue';
import { applyFilters, useDebounced } from '../../lib/filters';

const props = defineProps({
    generations: { type: Object, required: true },
    statuses: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const statusOptions = computed(() => props.statuses.map((value) => ({ value, label: value.charAt(0).toUpperCase() + value.slice(1) })));

const status = ref(props.filters.status ?? '');
const search = ref(props.filters.search ?? '');

function apply(overrides = {}) {
    applyFilters('/admin/generations', {
        status: status.value,
        search: search.value,
        user: props.filters.user?.id,
        ...overrides,
    });
}

watch(status, () => apply());
watch(search, useDebounced(() => apply()));
</script>

<template>
    <div class="space-y-6">
        <Head title="Generations" />

        <PageHeader
            title="Generations"
            description="Every image request, newest first, with the price range we expected and the price we charged. Images stay private to their owners."
        />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <FilterSelect v-model="status" label="Status" all-label="All statuses" :options="statusOptions" class="sm:w-44" />
            <SearchField v-model="search" label="Search prompts" class="sm:w-72" />

            <span
                v-if="filters.user"
                class="inline-flex items-center gap-1.5 self-start rounded-full border border-stone-200 bg-white py-1 pr-1 pl-3 text-sm shadow-xs sm:self-auto"
            >
                <span class="text-stone-500">User</span>
                <span class="font-medium text-stone-900">{{ filters.user.email }}</span>
                <button
                    type="button"
                    class="grid size-6 place-items-center rounded-full text-stone-400 hover:bg-stone-100 hover:text-stone-900"
                    :aria-label="`Show every user's requests, not only ${filters.user.email}'s`"
                    @click="apply({ user: null })"
                >
                    <Icon name="x" class="size-3.5" />
                </button>
            </span>

            <span class="text-sm text-stone-500 tabular-nums sm:ml-auto">{{ generations.meta.total.toLocaleString() }} matching</span>
        </div>

        <GenerationsTable :generations="generations.data" empty-text="No requests match." />

        <Pagination :links="generations.links" :meta="generations.meta" />
    </div>
</template>
