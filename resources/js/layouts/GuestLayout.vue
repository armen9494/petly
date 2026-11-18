<script setup>
import AuthShowcase from '../components/AuthShowcase.vue';
import BrandMark from '../components/BrandMark.vue';
</script>

<template>
    <div class="min-h-screen bg-white text-stone-900 lg:grid lg:grid-cols-2">
        <div class="flex min-h-screen flex-col px-6 py-8 sm:px-10">
            <BrandMark />

            <main class="flex flex-1 items-center justify-center py-12">
                <div class="w-full max-w-sm">
                    <slot />
                </div>
            </main>
        </div>

        <aside class="hidden border-l border-stone-200 bg-stone-50 lg:flex">
            <AuthShowcase />
        </aside>
    </div>
</template>
