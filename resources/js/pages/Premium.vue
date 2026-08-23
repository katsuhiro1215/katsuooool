<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ isPremium: boolean }>();

const PLANS = [
    {
        key: 'monthly',
        label: '月額プラン',
        price: '¥600',
        period: '/ 月',
        note: '',
    },
    {
        key: 'yearly',
        label: '年額プラン',
        price: '¥6,600',
        period: '/ 年',
        note: '月額換算で1ヶ月分お得',
    },
];
</script>

<template>
    <Head title="Premium - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Premium</h1>
                <p class="mt-2 text-sm text-gray-600">
                    画像系ツールのバッチ処理件数・レート制限が緩和され、ツール利用履歴を確認できるマイページが使えるようになります。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                v-if="isPremium"
                class="mb-8 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700"
            >
                すでにPremiumにご登録いただいています。
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="plan in PLANS"
                    :key="plan.key"
                    class="rounded-lg border border-gray-200 bg-white p-6"
                >
                    <h2 class="text-sm font-medium text-gray-700">
                        {{ plan.label }}
                    </h2>
                    <p class="mt-2 text-3xl font-bold text-gray-900">
                        {{ plan.price }}
                        <span class="text-sm font-normal text-gray-500">{{
                            plan.period
                        }}</span>
                    </p>
                    <p v-if="plan.note" class="mt-1 text-xs text-gray-500">
                        {{ plan.note }}
                    </p>
                    <Link
                        v-if="!isPremium"
                        :href="route('premium.checkout', { plan: plan.key })"
                        method="post"
                        as="button"
                        class="mt-4 block w-full rounded-md bg-gray-900 px-4 py-2 text-center text-sm font-medium text-white hover:bg-gray-700"
                    >
                        このプランで登録
                    </Link>
                </div>
            </div>

            <p class="mt-8 text-xs text-gray-500">
                価格は税込です。無料トライアルはありません。ご登録と同時に課金が開始されます。返金は行っておりません。解約はいつでも可能で、解約後もお支払い済みの期間終了までPremium機能をご利用いただけます。
            </p>
        </main>
    </div>
</template>
