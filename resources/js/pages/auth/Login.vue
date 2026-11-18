<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import SubmitButton from '../../components/SubmitButton.vue';
import TextField from '../../components/TextField.vue';

const page = usePage();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// An error can also arrive with a redirect here, such as "This account is
// inactive." after an admin deactivates a signed-in user. The form only
// collects errors from its own submissions, so start with the page's.
form.setError(page.props.errors);

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <div>
        <Head title="Log in" />

        <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
        <p class="mt-2 text-sm text-stone-500">Log in to keep creating. Your images and credits are where you left them.</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <TextField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
            <TextField v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />

            <label class="flex w-fit items-center gap-2 text-sm text-stone-600">
                <input v-model="form.remember" type="checkbox" class="size-4 accent-violet-600" />
                Remember me
            </label>

            <SubmitButton :processing="form.processing" processing-label="Logging in…">Log in</SubmitButton>
        </form>

        <p class="mt-8 text-center text-sm text-stone-500">
            New here?
            <Link href="/register" class="font-medium text-violet-700 hover:underline">Create an account</Link>
        </p>
    </div>
</template>
