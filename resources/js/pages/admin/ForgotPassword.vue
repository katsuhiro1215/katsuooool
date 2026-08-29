<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();

const form = useForm({ email: '' });

function submit() {
    form.post('/admin/forgot-password');
}
</script>

<template>
    <Head title="パスワードをお忘れですか? - Admin" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">
                パスワードをお忘れですか?
            </h1>
            <p class="mt-2 text-sm text-gray-600">
                登録済みのメールアドレスを入力してください。パスワード再設定用のリンクを送信します。
            </p>

            <p v-if="status" class="mt-4 text-sm text-green-700">
                {{ status }}
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label
                        for="email"
                        class="block text-sm font-medium text-gray-700"
                        >メールアドレス</label
                    >
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    送信する
                </button>
            </form>
        </div>
    </div>
</template>
