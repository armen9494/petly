<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DeviceIcon from '../../components/DeviceIcon.vue';
import SubmitButton from '../../components/SubmitButton.vue';
import TextField from '../../components/TextField.vue';
import { formatDateTime, timeAgo } from '../../lib/time';

const props = defineProps({
    sessions: { type: Array, required: true },
});

const page = usePage();

const profileForm = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updateProfile() {
    profileForm.patch('/profile', { preserveScroll: true });
}

function updatePassword() {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}

const hasOtherDevices = computed(() => props.sessions.some((session) => !session.is_current_device));

/**
 * The key of the session being signed out, or "others" while signing out all other devices.
 */
const signingOut = ref(null);

function signOut(url, key) {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (signingOut.value = key),
        onFinish: () => (signingOut.value = null),
    });
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-8">
        <Head title="Profile" />

        <div>
            <h1 class="text-lg font-semibold">Profile</h1>
            <p class="mt-1 text-sm text-stone-500">Manage your account details and password.</p>
        </div>

        <form class="rounded-2xl border border-stone-200 bg-white p-5" @submit.prevent="updateProfile">
            <h2 class="font-semibold">Profile information</h2>
            <p class="mt-1 text-sm text-stone-500">Your name and the email you log in with.</p>

            <div class="mt-5 space-y-5">
                <TextField v-model="profileForm.name" label="Name" autocomplete="name" :error="profileForm.errors.name" />
                <TextField v-model="profileForm.email" label="Email" type="email" autocomplete="email" :error="profileForm.errors.email" />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <p v-if="profileForm.recentlySuccessful" role="status" class="text-center text-sm text-emerald-700">Saved.</p>
                <SubmitButton :processing="profileForm.processing" processing-label="Saving…" class="sm:w-auto sm:px-6">Save</SubmitButton>
            </div>
        </form>

        <form class="rounded-2xl border border-stone-200 bg-white p-5" @submit.prevent="updatePassword">
            <h2 class="font-semibold">Change password</h2>
            <p class="mt-1 text-sm text-stone-500">Enter your current password, then choose a new one.</p>

            <div class="mt-5 space-y-5">
                <TextField
                    v-model="passwordForm.current_password"
                    label="Current password"
                    type="password"
                    autocomplete="current-password"
                    :error="passwordForm.errors.current_password"
                />
                <TextField
                    v-model="passwordForm.password"
                    label="New password"
                    type="password"
                    autocomplete="new-password"
                    :error="passwordForm.errors.password"
                />
                <TextField
                    v-model="passwordForm.password_confirmation"
                    label="Confirm new password"
                    type="password"
                    autocomplete="new-password"
                    :error="passwordForm.errors.password_confirmation"
                />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <p v-if="passwordForm.recentlySuccessful" role="status" class="text-center text-sm text-emerald-700">Password updated.</p>
                <SubmitButton :processing="passwordForm.processing" processing-label="Updating…" class="sm:w-auto sm:px-6">Update password</SubmitButton>
            </div>
        </form>

        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Signed-in devices</h2>
            <p class="mt-1 text-sm text-stone-500">Devices that are signed in to your account. Sign out any you don't recognize.</p>

            <ul class="mt-5 divide-y divide-stone-100">
                <li v-for="session in sessions" :key="session.key" class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                    <DeviceIcon :mobile="session.is_mobile" class="size-8 shrink-0 text-stone-400" />
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
                            {{ session.device }}
                            <span v-if="session.is_current_device" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                This device
                            </span>
                        </p>
                        <p class="mt-0.5 truncate text-xs text-stone-500">
                            {{ session.ip_address ?? 'Unknown IP address' }} ·
                            <template v-if="session.is_current_device">Active now</template>
                            <time v-else :datetime="session.last_active_at" :title="formatDateTime(session.last_active_at)">
                                Active {{ timeAgo(session.last_active_at) }}
                            </time>
                        </p>
                    </div>
                    <button
                        v-if="!session.is_current_device"
                        type="button"
                        :disabled="signingOut !== null"
                        class="shrink-0 rounded-lg border border-stone-300 px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-600 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="signOut(`/profile/sessions/${session.key}`, session.key)"
                    >
                        {{ signingOut === session.key ? 'Signing out…' : 'Sign out' }}
                    </button>
                </li>
            </ul>

            <div v-if="hasOtherDevices" class="mt-5 flex justify-end border-t border-stone-100 pt-5">
                <button
                    type="button"
                    :disabled="signingOut !== null"
                    class="w-full rounded-lg bg-stone-900 px-6 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-stone-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-600 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    @click="signOut('/profile/sessions', 'others')"
                >
                    {{ signingOut === 'others' ? 'Signing out…' : 'Sign out all other devices' }}
                </button>
            </div>
        </section>
    </div>
</template>
