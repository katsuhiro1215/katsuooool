<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps<{
    email: string;
    twoFactorConfirmed: boolean;
    status?: string;
}>();

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const emailForm = useForm({
    current_password: '',
    email: props.email,
});

const recoveryForm = useForm({
    current_password: '',
});

function submitPassword() {
    passwordForm.put('/admin/settings/password', {
        onFinish: () => passwordForm.reset(),
    });
}

function submitEmail() {
    emailForm.put('/admin/settings/email', {
        onFinish: () => emailForm.reset('current_password'),
    });
}

function submitRecoveryCodes() {
    recoveryForm.post('/admin/two-factor-setup/recovery-codes', {
        onFinish: () => recoveryForm.reset(),
    });
}
</script>

<template>
    <Head title="設定 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">設定</h1>
        </template>

        <div class="max-w-xl space-y-8">
            <p
                v-if="status"
                class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700"
            >
                {{ status }}
            </p>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-bold text-gray-900">パスワード変更</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitPassword">
                    <div>
                        <label
                            for="current_password"
                            class="block text-sm font-medium text-gray-700"
                            >現在のパスワード</label
                        >
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="passwordForm.errors.current_password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ passwordForm.errors.current_password }}
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
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="passwordForm.errors.password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ passwordForm.errors.password }}
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
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :disabled="passwordForm.processing"
                    >
                        パスワードを変更する
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-bold text-gray-900">
                    メールアドレス変更
                </h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEmail">
                    <div>
                        <label
                            for="email"
                            class="block text-sm font-medium text-gray-700"
                            >新しいメールアドレス</label
                        >
                        <input
                            id="email"
                            v-model="emailForm.email"
                            type="email"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="emailForm.errors.email"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ emailForm.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="email_current_password"
                            class="block text-sm font-medium text-gray-700"
                        >
                            現在のパスワード
                        </label>
                        <input
                            id="email_current_password"
                            v-model="emailForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="emailForm.errors.current_password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ emailForm.errors.current_password }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :disabled="emailForm.processing"
                    >
                        メールアドレスを変更する
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-bold text-gray-900">二段階認証</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ twoFactorConfirmed ? '設定済みです。' : '未設定です。' }}
                </p>
                <form
                    class="mt-4 space-y-4"
                    @submit.prevent="submitRecoveryCodes"
                >
                    <div>
                        <label
                            for="recovery_current_password"
                            class="block text-sm font-medium text-gray-700"
                        >
                            現在のパスワード
                        </label>
                        <input
                            id="recovery_current_password"
                            v-model="recoveryForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="recoveryForm.errors.current_password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ recoveryForm.errors.current_password }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="recoveryForm.processing"
                    >
                        リカバリーコードを再生成する
                    </button>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
