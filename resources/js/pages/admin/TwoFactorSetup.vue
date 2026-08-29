<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    confirmed: boolean;
    qrCodeSvg: string | null;
    recoveryCodes: string[] | null;
}>();

const form = useForm({
    code: '',
});

function submit() {
    form.post('/admin/two-factor-setup/confirm', {
        errorBag: 'confirmTwoFactorAuthentication',
        onFinish: () => form.reset('code'),
    });
}
</script>

<template>
    <Head title="二段階認証の設定 - Admin" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12"
    >
        <div
            class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">二段階認証の設定</h1>
            <p class="mt-2 text-sm text-gray-600">
                管理者アカウントには二段階認証の設定が必須です。設定が完了するまで他の画面は利用できません。
            </p>

            <div
                v-if="recoveryCodes"
                class="mt-6 rounded-md border border-amber-300 bg-amber-50 p-4"
            >
                <p class="text-sm font-medium text-amber-800">
                    リカバリーコード(この画面でのみ表示されます。安全な場所に保存してください)
                </p>
                <ul
                    class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs text-amber-900"
                >
                    <li
                        v-for="recoveryCode in recoveryCodes"
                        :key="recoveryCode"
                    >
                        {{ recoveryCode }}
                    </li>
                </ul>
            </div>

            <template v-if="!confirmed">
                <div
                    v-if="qrCodeSvg"
                    class="mt-6 rounded-md border border-gray-200 p-4"
                    v-html="qrCodeSvg"
                />

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label
                            for="code"
                            class="block text-sm font-medium text-gray-700"
                        >
                            認証アプリに表示されたコード
                        </label>
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

                    <button
                        type="submit"
                        class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :disabled="form.processing"
                    >
                        確認する
                    </button>
                </form>
            </template>

            <p v-else class="mt-6 text-sm text-green-700">
                二段階認証は設定済みです。
            </p>
        </div>
    </div>
</template>
