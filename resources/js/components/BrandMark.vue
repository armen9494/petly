<template>
    <span class="inline-flex items-center gap-2 font-semibold tracking-tight text-stone-900">
        <svg viewBox="0 0 32 32" class="size-7 shrink-0" aria-hidden="true">
            <rect width="32" height="32" rx="8" class="fill-violet-600" />
            <g class="fill-white">
                <path
                    v-for="angle in [0, 90, 180, 270]"
                    :key="angle"
                    d="M16 5.5c4 3.3 4.2 7.6 0 10.5c-4.2-2.9-4-7.2 0-10.5z"
                    :transform="`rotate(${angle} 16 16)`"
                />
            </g>
        </svg>
        Petly
    </span>
</template>
