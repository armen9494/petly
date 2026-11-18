<script setup>
/**
 * Why a generation failed, for admins: the message, then the error code and
 * Pixelforge id to trace it with. A request Pixelforge never accepted has a
 * code but no Pixelforge id. With truncate, each line stays on one line and
 * shows in full on hover.
 */
defineProps({
    generation: { type: Object, required: true },
    truncate: { type: Boolean, default: false },
});
</script>

<template>
    <div class="min-w-0 text-xs">
        <p class="text-red-700" :class="{ truncate }" :title="truncate ? generation.error_message : undefined">
            {{ generation.error_message ?? 'No error message recorded.' }}
        </p>
        <p v-if="generation.error_code || generation.pixelforge_id" class="mt-0.5 font-mono text-stone-500" :class="{ truncate }">
            <span v-if="generation.error_code">{{ generation.error_code }}</span>
            <template v-if="generation.error_code && generation.pixelforge_id"> · </template>
            <span v-if="generation.pixelforge_id" class="select-all" :title="truncate ? generation.pixelforge_id : undefined">{{ generation.pixelforge_id }}</span>
        </p>
    </div>
</template>
