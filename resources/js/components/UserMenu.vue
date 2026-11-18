<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const open = ref(false);
const root = ref(null);

const initials = computed(() =>
    props.user.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

function closeWhenClickedOutside(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
}

function closeOnEscape(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', closeWhenClickedOutside);
    document.addEventListener('keydown', closeOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeWhenClickedOutside);
    document.removeEventListener('keydown', closeOnEscape);
});

const ITEM = 'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-stone-700 hover:bg-stone-100 hover:text-stone-900';
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex items-center gap-1 rounded-full p-0.5 pr-1.5 hover:bg-stone-100"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-label="`Account menu for ${user.name}`"
            @click="open = !open"
        >
            <span class="grid size-8 place-items-center rounded-full bg-violet-100 text-xs font-semibold text-violet-700">{{ initials }}</span>
            <Icon name="chevron-down" class="size-3.5 text-stone-400" />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="open" role="menu" class="absolute right-0 z-30 mt-2 w-60 rounded-xl border border-stone-200 bg-white p-1 shadow-lg">
                <div class="px-2.5 py-2">
                    <p class="flex items-center gap-2 truncate text-sm font-medium text-stone-900">
                        {{ user.name }}
                        <span v-if="user.is_admin" class="rounded bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700">Admin</span>
                    </p>
                    <p class="truncate text-xs text-stone-500">{{ user.email }}</p>
                </div>
                <div class="my-1 h-px bg-stone-100"></div>
                <Link href="/profile" role="menuitem" :class="ITEM" @click="open = false">
                    <Icon name="user" class="size-4 text-stone-400" />
                    Profile
                </Link>
                <Link href="/credits" role="menuitem" :class="ITEM" @click="open = false">
                    <Icon name="wallet" class="size-4 text-stone-400" />
                    Credits
                </Link>
                <Link v-if="user.is_admin" href="/admin" role="menuitem" :class="ITEM" @click="open = false">
                    <Icon name="shield" class="size-4 text-stone-400" />
                    Admin panel
                </Link>
                <Link href="/logout" method="post" as="button" role="menuitem" :class="ITEM">
                    <Icon name="logout" class="size-4 text-stone-400" />
                    Log out
                </Link>
            </div>
        </Transition>
    </div>
</template>
