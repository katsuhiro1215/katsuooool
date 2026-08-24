<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

defineProps<{ success: boolean }>();

const REFERRAL_OPTIONS = [
    { value: 'Google', label: '検索エンジン(Google, Yahoo!など)' },
    { value: 'X', label: 'X(Twitter)' },
    { value: 'Instagram', label: 'Instagram' },
    { value: 'Referral', label: '知人の紹介' },
    { value: 'Other', label: 'その他' },
];

const CATEGORY_OPTIONS = [
    { value: 'bug', label: '不具合の報告' },
    { value: 'premium', label: 'Premiumについて' },
    { value: 'feature', label: '機能のご要望' },
    { value: 'partnership', label: '取材・提携のご相談' },
    { value: 'other', label: 'その他' },
];

const form = useForm({
    referral: '',
    category: '',
    name: '',
    name_kana: '',
    company: '',
    tel: '',
    email: '',
    message: '',
    privacy_agree: false,
    not_sales_agree: false,
});

function submit() {
    form.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="お問い合わせ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    お問い合わせ
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <p
                v-if="success"
                class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700"
            >
                お問い合わせいただき、ありがとうございます。1〜2営業日以内にご返信いたします。
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="referral"
                    >
                        当サイトを知ったきっかけは?
                    </label>
                    <select
                        id="referral"
                        v-model="form.referral"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="">選択してください</option>
                        <option
                            v-for="option in REFERRAL_OPTIONS"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="category"
                    >
                        ご検討中の内容<span class="ml-1 text-red-600"
                            >必須</span
                        >
                    </label>
                    <select
                        id="category"
                        v-model="form.category"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="">選択してください</option>
                        <option
                            v-for="option in CATEGORY_OPTIONS"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.category"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.category }}
                    </p>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="name"
                    >
                        お名前・担当者<span class="ml-1 text-red-600"
                            >必須</span
                        >
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="山田 太郎"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="name_kana"
                    >
                        ふりがな<span class="ml-1 text-red-600">必須</span>
                    </label>
                    <input
                        id="name_kana"
                        v-model="form.name_kana"
                        type="text"
                        placeholder="やまだ たろう"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.name_kana"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.name_kana }}
                    </p>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="company"
                    >
                        会社名・屋号
                    </label>
                    <input
                        id="company"
                        v-model="form.company"
                        type="text"
                        placeholder="株式会社かつコード"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="tel"
                    >
                        電話番号<span class="ml-1 text-red-600">必須</span>
                    </label>
                    <input
                        id="tel"
                        v-model="form.tel"
                        type="tel"
                        placeholder="090-1234-5678"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.tel" class="mt-1 text-xs text-red-600">
                        {{ form.errors.tel }}
                    </p>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="email"
                    >
                        メールアドレス<span class="ml-1 text-red-600"
                            >必須</span
                        >
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="example@katsucode.jp"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"
                        for="message"
                    >
                        ご依頼・ご相談内容<span class="ml-1 text-red-600"
                            >必須</span
                        >
                    </label>
                    <textarea
                        id="message"
                        v-model="form.message"
                        rows="6"
                        placeholder="お気軽にご相談内容をご記入ください。"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.message"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.message }}
                    </p>
                </div>

                <p class="text-xs text-gray-500">
                    <Link
                        href="/legal/privacy-policy"
                        class="underline hover:text-gray-700"
                        target="_blank"
                    >
                        プライバシーポリシーを確認する
                    </Link>
                </p>

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input
                            v-model="form.privacy_agree"
                            type="checkbox"
                            class="mt-0.5"
                        />
                        <span>プライバシーポリシーに同意する</span>
                    </label>
                    <p
                        v-if="form.errors.privacy_agree"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.privacy_agree }}
                    </p>
                </div>

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input
                            v-model="form.not_sales_agree"
                            type="checkbox"
                            class="mt-0.5"
                        />
                        <span
                            >営業・勧誘を目的としたお問い合わせではありません。</span
                        >
                    </label>
                    <p
                        v-if="form.errors.not_sales_agree"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors.not_sales_agree }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    {{ form.processing ? '送信しています...' : '送信する' }}
                </button>
            </form>
        </main>

        <AppFooter />
    </div>
</template>
