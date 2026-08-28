<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from '@/components/Checkbox.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="ログイン - katsuooool" />

        <h1 class="mb-6 text-center text-lg font-bold text-gray-900">
            ログイン
        </h1>

        <div
            v-if="status"
            class="mb-4 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="メールアドレス" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="パスワード" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >ログイン状態を保持する</span
                    >
                </label>
            </div>

            <PrimaryButton
                class="mt-6 w-full justify-center"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                ログイン
            </PrimaryButton>

            <div class="mt-4 flex items-center justify-between text-sm">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-gray-600 underline hover:text-gray-900"
                >
                    パスワードをお忘れですか？
                </Link>
                <Link
                    :href="route('register')"
                    class="text-gray-600 underline hover:text-gray-900"
                >
                    新規登録はこちら
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
