<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { CATEGORY_TABS } from '@/lib/toolFilter';

const CATEGORIES = CATEGORY_TABS.filter((tab) => tab.key !== 'all');
</script>

<template>
    <Head title="Service - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Service"
                description="katsuoooolが提供するツールカテゴリのご紹介です。"
            />

            <PageMain>
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <li
                        v-for="category in CATEGORIES"
                        :key="category.key"
                        class="rounded-lg border border-gray-200 bg-white p-4"
                    >
                        <span class="text-sm font-semibold text-gray-900">{{
                            category.label
                        }}</span>
                    </li>
                </ul>

                <p class="mt-8 text-sm text-gray-600">
                    すべてのツールは
                    <a href="/" class="underline hover:text-gray-900"
                        >トップページ</a
                    >の検索・カテゴリタブから探せます。
                </p>
            </PageMain>
        </div>
    </PublicLayout>
</template>
