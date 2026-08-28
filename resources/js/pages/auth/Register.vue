<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

const PLANS = [
    { key: 'monthly', label: '月額プラン', price: '¥600 / 月' },
    { key: 'yearly', label: '年額プラン', price: '¥6,600 / 年' },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    want_premium: false,
    plan: 'monthly',
    terms_agree: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="新規登録 - katsuooool" />

        <h1 class="mb-6 text-center text-lg font-bold text-gray-900">
            新規登録
        </h1>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="お名前" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="メールアドレス" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
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
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="パスワード(確認)"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div
                class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4"
            >
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input
                        v-model="form.want_premium"
                        type="checkbox"
                        class="mt-0.5 rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-500"
                    />
                    <span class="font-medium"
                        >Premiumも同時に申し込む(月額¥600〜)</span
                    >
                </label>

                <div v-if="form.want_premium" class="mt-4 space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <label
                            v-for="plan in PLANS"
                            :key="plan.key"
                            class="flex flex-1 cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                            :class="{
                                'border-amber-500 ring-1 ring-amber-500':
                                    form.plan === plan.key,
                            }"
                        >
                            <input
                                v-model="form.plan"
                                type="radio"
                                :value="plan.key"
                                class="text-amber-600 focus:ring-amber-500"
                            />
                            <span>{{ plan.label }}</span>
                            <span class="ml-auto text-gray-500">{{
                                plan.price
                            }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.plan" />

                    <div>
                        <label
                            class="flex items-start gap-2 text-sm text-gray-700"
                        >
                            <input
                                v-model="form.terms_agree"
                                type="checkbox"
                                class="mt-0.5 rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-500"
                            />
                            <span>
                                <Link
                                    href="/legal/terms"
                                    class="underline hover:text-gray-900"
                                    target="_blank"
                                    >利用規約</Link
                                >に同意する
                            </span>
                        </label>
                        <InputError :message="form.errors.terms_agree" />
                    </div>
                </div>
            </div>

            <PrimaryButton
                class="mt-6 w-full justify-center"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                {{
                    form.want_premium
                        ? 'アカウントを作成してPremiumへ進む'
                        : 'アカウントを作成する'
                }}
            </PrimaryButton>

            <p class="mt-4 text-center text-sm text-gray-600">
                すでにアカウントをお持ちですか？
                <Link
                    :href="route('login')"
                    class="text-gray-900 underline hover:text-gray-700"
                >
                    ログイン
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
