<script setup>
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';

const page = usePage();
const message = ref(null);
let timer = null;

watch(
    () => page.flash?.success,
    (success) => {
        if (!success) {
            return;
        }

        message.value = success;
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), 5000);
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="message"
            role="status"
            class="fixed right-4 bottom-4 z-50 flex max-w-sm items-start gap-3 rounded-xl bg-stone-900 px-4 py-3 text-sm text-white shadow-lg"
        >
            <Icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-400" />
            <p class="flex-1">{{ message }}</p>
            <button type="button" class="text-stone-400 hover:text-white" aria-label="Dismiss" @click="message = null">
                <Icon name="x" class="size-4" />
            </button>
        </div>
    </Transition>
</template>
