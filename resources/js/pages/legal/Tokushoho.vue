<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

interface LegalItem {
    label: string;
    value: string;
}

const ITEMS: LegalItem[] = [
    { label: '販売事業者', value: '【事業者名を入力してください】' },
    { label: '運営責任者', value: '【運営責任者名を入力してください】' },
    { label: '所在地', value: '【所在地を入力してください】' },
    {
        label: '連絡先',
        value: '【メールアドレス等の連絡先を入力してください】',
    },
    { label: '販売価格', value: '月額¥600(税込)・年額¥6,600(税込)' },
    { label: '商品代金以外の必要料金', value: 'なし' },
    { label: 'お支払い方法', value: 'クレジットカード決済(Stripe)' },
    {
        label: 'お支払い時期',
        value: 'ご登録時に即時課金。以降、選択したプランの周期ごとに自動更新',
    },
    { label: 'サービス提供時期', value: '決済完了後、即時利用可能' },
    {
        label: '返品・キャンセルについて',
        value: 'デジタルサービスの性質上、返金は行っておりません。解約はいつでも可能で、解約後もお支払い済みの期間終了まではサービスをご利用いただけます。',
    },
];
</script>

<template>
    <Head title="特定商取引法に基づく表記 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="特定商取引法に基づく表記"
                description=""
                narrow
            />

            <PageMain narrow>
                <dl
                    class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white"
                >
                    <div
                        v-for="item in ITEMS"
                        :key="item.label"
                        class="grid grid-cols-1 gap-1 px-4 py-4 sm:grid-cols-3 sm:gap-4"
                    >
                        <dt class="text-sm font-medium text-gray-500">
                            {{ item.label }}
                        </dt>
                        <dd class="text-sm text-gray-900 sm:col-span-2">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>
            </PageMain>
        </div>
    </PublicLayout>
</template>
