<script setup>
defineProps({
    user: { type: Object, required: true },
});
</script>

<template>
    <span v-if="user.is_admin || !user.is_active" class="inline-flex gap-1.5">
        <span v-if="user.is_admin" class="rounded bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700">Admin</span>
        <span v-if="!user.is_active" class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">Inactive</span>
    </span>
</template>
