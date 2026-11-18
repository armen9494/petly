<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SubmitButton from '../../components/SubmitButton.vue';
import TextField from '../../components/TextField.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <div>
        <Head title="Create an account" />

        <h1 class="text-2xl font-semibold tracking-tight">Create an account</h1>
        <p class="mt-2 text-sm text-stone-500">Generate images and pay only for what succeeds.</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <TextField v-model="form.name" label="Name" autocomplete="name" :error="form.errors.name" />
            <TextField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
            <TextField v-model="form.password" label="Password" type="password" autocomplete="new-password" :error="form.errors.password" />
            <TextField
                v-model="form.password_confirmation"
                label="Confirm password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <SubmitButton :processing="form.processing" processing-label="Creating account…">Create account</SubmitButton>
        </form>

        <p class="mt-8 text-center text-sm text-stone-500">
            Already have an account?
            <Link href="/login" class="font-medium text-violet-700 hover:underline">Log in</Link>
        </p>
    </div>
</template>
