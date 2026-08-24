<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

interface PostDetail {
    title: string;
    type: string;
    published_at: string;
    body_html: string;
}

defineProps<{ post: PostDetail }>();

const TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('ja-JP');
}
</script>

<template>
    <Head :title="`${post.title} - katsuooool`" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a
                    href="/blog"
                    class="text-sm text-gray-500 hover:text-gray-700"
                    >← お知らせ一覧に戻る</a
                >
                <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                    <span>{{ TYPE_LABELS[post.type] }}</span>
                    <span>{{ formatDate(post.published_at) }}</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">
                    {{ post.title }}
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="prose prose-sm max-w-none rounded-lg border border-gray-200 bg-white p-6"
                v-html="post.body_html"
            ></div>
        </main>

        <AppFooter />
    </div>
</template>
