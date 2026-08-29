<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const useRecoveryCode = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function submit() {
    form.post('/admin/two-factor-challenge', {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="二段階認証 - Admin" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">二段階認証</h1>
            <p class="mt-2 text-sm text-gray-600">
                認証アプリに表示されているコードを入力してください。
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div v-if="!useRecoveryCode">
                    <label
                        for="code"
                        class="block text-sm font-medium text-gray-700"
                        >認証コード</label
                    >
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div v-else>
                    <label
                        for="recovery_code"
                        class="block text-sm font-medium text-gray-700"
                        >リカバリーコード</label
                    >
                    <input
                        id="recovery_code"
                        v-model="form.recovery_code"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    確認する
                </button>

                <button
                    type="button"
                    class="w-full text-center text-sm text-gray-500 underline"
                    @click="useRecoveryCode = !useRecoveryCode"
                >
                    {{
                        useRecoveryCode
                            ? '認証コードを使う'
                            : 'リカバリーコードを使う'
                    }}
                </button>
            </form>
        </div>
    </div>
</template>
