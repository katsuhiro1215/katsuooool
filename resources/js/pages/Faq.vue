<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

interface FaqItem {
    question: string;
    answer: string;
}

const ITEMS: FaqItem[] = [
    {
        question: '無料でどこまで使えますか?',
        answer: '既存のツールはすべて無料でご利用いただけます。Premiumプランに登録すると、画像系ツールの一度に処理できる枚数が増えたり、レート制限が緩和されたりします。',
    },
    {
        question: 'アップロードしたファイルはどうなりますか?',
        answer: '処理のために一時的にサーバーへ保存されますが、一定時間の経過後に自動的に削除されます。処理結果のダウンロードURLにも有効期限があります。',
    },
    {
        question: '会員登録は必要ですか?',
        answer: 'ほとんどのツールは会員登録なしでご利用いただけます。Premiumプランへの登録やマイページ機能をご利用の場合のみ、会員登録が必要です。',
    },
    {
        question: 'Premiumプランを解約するとどうなりますか?',
        answer: '解約はいつでも可能です。解約後もお支払い済みの期間終了まではPremium機能をご利用いただけます。返金は行っておりません。',
    },
    {
        question: '対応しているファイル形式を教えてください。',
        answer: '画像系ツールは主にJPEG・PNG・WebP(一部AVIFにも対応)を扱います。ツールごとの詳細な対応形式は、各ツールのページをご確認ください。',
    },
];
</script>

<template>
    <Head title="よくある質問 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    よくある質問
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="space-y-3">
                <details
                    v-for="item in ITEMS"
                    :key="item.question"
                    class="rounded-lg border border-gray-200 bg-white p-4"
                >
                    <summary
                        class="cursor-pointer text-sm font-medium text-gray-900"
                    >
                        {{ item.question }}
                    </summary>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700">
                        {{ item.answer }}
                    </p>
                </details>
            </div>
        </main>

        <AppFooter />
    </div>
</template>
