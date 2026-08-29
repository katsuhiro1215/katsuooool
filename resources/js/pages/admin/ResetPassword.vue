<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/admin/reset-password');
}
</script>

<template>
    <Head title="パスワードの再設定 - Admin" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">パスワードの再設定</h1>

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

                <div>
                    <label
                        for="password"
                        class="block text-sm font-medium text-gray-700"
                        >新しいパスワード</label
                    >
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        12文字以上、大文字・小文字・数字・記号をそれぞれ1文字以上含めてください。
                    </p>
                    <p
                        v-if="form.errors.password"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label
                        for="password_confirmation"
                        class="block text-sm font-medium text-gray-700"
                    >
                        新しいパスワード(確認)
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    パスワードを再設定する
                </button>
            </form>
        </div>
    </div>
</template>
