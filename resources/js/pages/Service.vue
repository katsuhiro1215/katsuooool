<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';
import { CATEGORY_TABS } from '@/lib/toolFilter';

const CATEGORIES = CATEGORY_TABS.filter((tab) => tab.key !== 'all');
</script>

<template>
    <Head title="Service - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Service</h1>
                <p class="mt-2 text-sm text-gray-600">
                    katsuoooolが提供するツールカテゴリのご紹介です。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
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
        </main>

        <AppFooter />
    </div>
</template>
