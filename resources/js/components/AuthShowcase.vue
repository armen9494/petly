<script setup>
import Icon from './Icon.vue';
import StatusBadge from './StatusBadge.vue';
import Usd from './Usd.vue';

const FEATURES = [
    { icon: 'receipt', label: 'See the price range before you generate' },
    { icon: 'clock', label: 'Follow each image from queued to done' },
    { icon: 'check', label: 'Failed images are never charged' },
];
</script>

<template>
    <div class="flex flex-1 flex-col justify-center px-12 py-16 xl:px-20">
        <div class="mx-auto w-full max-w-md">
            <p class="text-3xl font-semibold tracking-tight text-balance text-stone-900">
                Create images.
                <span class="text-violet-700">Pay only for the ones that work.</span>
            </p>
            <p class="mt-4 text-base/7 text-pretty text-stone-600">
                Petly shows the cost up front and charges the actual price once your image is ready.
            </p>

            <ul class="mt-8 space-y-3">
                <li v-for="feature in FEATURES" :key="feature.label" class="flex items-center gap-3 text-sm text-stone-700">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-violet-50 text-violet-700 ring-1 ring-violet-100">
                        <Icon :name="feature.icon" class="size-3.5" />
                    </span>
                    {{ feature.label }}
                </li>
            </ul>

            <article aria-hidden="true" class="mt-12 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm select-none">
                <div class="relative aspect-3/2">
                    <svg viewBox="0 0 300 200" preserveAspectRatio="xMidYMid slice" class="block size-full">
                        <defs>
                            <linearGradient id="showcase-sky" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#1e1b4b" />
                                <stop offset="0.45" stop-color="#86198f" />
                                <stop offset="0.78" stop-color="#f472b6" />
                                <stop offset="1" stop-color="#fcd34d" />
                            </linearGradient>
                            <linearGradient id="showcase-sea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#3b0764" />
                                <stop offset="1" stop-color="#0c0a09" />
                            </linearGradient>
                            <linearGradient id="showcase-beam" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0" stop-color="#fef3c7" stop-opacity="0.6" />
                                <stop offset="1" stop-color="#fef3c7" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <rect width="300" height="134" fill="url(#showcase-sky)" />
                        <circle cx="210" cy="132" r="44" fill="#fde68a" opacity="0.25" />
                        <circle cx="210" cy="132" r="24" fill="#fde68a" />
                        <path d="M120 132 L150 120 L176 126 L196 116 L224 124 L250 114 L282 124 L300 120 L300 132 Z" fill="#581c87" opacity="0.7" />
                        <rect y="132" width="300" height="68" fill="url(#showcase-sea)" />
                        <g fill="#fde68a">
                            <rect x="190" y="138" width="40" height="2.5" rx="1.25" opacity="0.6" />
                            <rect x="196" y="146" width="28" height="2.5" rx="1.25" opacity="0.45" />
                            <rect x="201" y="154" width="18" height="2.5" rx="1.25" opacity="0.35" />
                        </g>
                        <polygon points="75,54 300,30 300,78" fill="url(#showcase-beam)" />
                        <path d="M0 200 L0 112 L34 104 L58 98 L86 100 L104 112 L116 132 L124 150 L132 200 Z" fill="#1c1917" />
                        <polygon points="66,99 78,99 76,62 68,62" fill="#f5f5f4" />
                        <polygon points="66.7,86 77.3,86 77,80 67,80" fill="#e11d48" />
                        <polygon points="67.35,74 76.65,74 76.3,68 67.7,68" fill="#e11d48" />
                        <rect x="66" y="60" width="12" height="2.5" fill="#1c1917" />
                        <rect x="68.5" y="52" width="7" height="8" rx="1" fill="#fef3c7" />
                        <polygon points="67.5,52 76.5,52 72,46" fill="#1c1917" />
                    </svg>
                    <StatusBadge status="succeeded" class="absolute top-3 left-3" />
                </div>

                <div class="flex flex-col gap-3 p-4 text-sm">
                    <p class="text-stone-800">Lighthouse on a sea cliff at dusk, soft watercolor</p>
                    <p class="text-xs text-stone-500">Landscape 1536x1024 · high quality · just now</p>
                    <dl class="grid grid-cols-2 gap-3 border-t border-stone-100 pt-3">
                        <div>
                            <dt class="text-xs text-stone-500">Estimate</dt>
                            <dd class="text-stone-700"><Usd value="0.042000000" /> – <Usd value="0.051000000" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Actual cost</dt>
                            <dd class="font-semibold text-stone-900"><Usd value="0.046125000" /></dd>
                        </div>
                    </dl>
                </div>
            </article>
        </div>
    </div>
</template>
