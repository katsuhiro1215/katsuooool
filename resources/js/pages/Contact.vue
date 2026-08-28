<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

interface ContactCategory {
    id: string;
    name: string;
}

defineProps<{
    success: boolean;
    error: boolean;
    categories: ContactCategory[];
}>();

const REFERRAL_OPTIONS = [
    { value: 'Google', label: '検索エンジン(Google, Yahoo!など)' },
    { value: 'X', label: 'X(Twitter)' },
    { value: 'Instagram', label: 'Instagram' },
    { value: 'Referral', label: '知人の紹介' },
    { value: 'Other', label: 'その他' },
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

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader title="お問い合わせ" description="" />

            <PageMain>
                <p
                    v-if="success"
                    class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700"
                >
                    お問い合わせいただき、ありがとうございます。1〜2営業日以内にご返信いたします。
                </p>

                <p
                    v-if="error"
                    class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                >
                    送信に失敗しました。お手数ですが、時間をおいて再度お試しください。
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
                            :disabled="categories.length === 0"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-gray-100"
                        >
                            <option value="">選択してください</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <p
                            v-if="categories.length === 0"
                            class="mt-1 text-xs text-red-600"
                        >
                            現在この項目を読み込めません。時間をおいて再度アクセスしてください。
                        </p>
                        <p
                            v-else-if="form.errors.category"
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
                        <p
                            v-if="form.errors.tel"
                            class="mt-1 text-xs text-red-600"
                        >
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
                        <label
                            class="flex items-start gap-2 text-sm text-gray-700"
                        >
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
                        <label
                            class="flex items-start gap-2 text-sm text-gray-700"
                        >
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
                        :disabled="form.processing || categories.length === 0"
                    >
                        {{ form.processing ? '送信しています...' : '送信する' }}
                    </button>
                </form>
            </PageMain>
        </div>
    </PublicLayout>
</template>
