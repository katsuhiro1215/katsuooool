<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

interface PostSummary {
    title: string;
    slug: string;
    type: string;
    published_at: string;
    excerpt: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
}

defineProps<{ posts: Paginated<PostSummary> }>();

const TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

const TYPE_BADGE_CLASSES: Record<string, string> = {
    update: 'bg-blue-50 text-blue-700',
    fix: 'bg-red-50 text-red-700',
    announcement: 'bg-gray-100 text-gray-700',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('ja-JP');
}
</script>

<template>
    <Head title="お知らせ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">お知らせ</h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div v-if="posts.data.length === 0" class="text-sm text-gray-500">
                お知らせはまだありません。
            </div>

            <ul v-else class="space-y-4">
                <li
                    v-for="post in posts.data"
                    :key="post.slug"
                    class="rounded-lg border border-gray-200 bg-white p-5"
                >
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span
                            class="rounded-full px-2 py-0.5 font-medium"
                            :class="TYPE_BADGE_CLASSES[post.type]"
                        >
                            {{ TYPE_LABELS[post.type] }}
                        </span>
                        <span>{{ formatDate(post.published_at) }}</span>
                    </div>
                    <Link
                        :href="`/blog/${post.slug}`"
                        class="mt-2 block text-base font-semibold text-gray-900 hover:underline"
                    >
                        {{ post.title }}
                    </Link>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ post.excerpt }}
                    </p>
                </li>
            </ul>

            <nav
                v-if="posts.links.length > 3"
                class="mt-6 flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in posts.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm',
                        link.active
                            ? 'bg-gray-900 text-white'
                            : 'text-gray-700 hover:bg-gray-100',
                        link.url === null &&
                            'pointer-events-none text-gray-300',
                    ]"
                >
                    <span v-html="link.label"></span>
                </Link>
            </nav>
        </main>

        <AppFooter />
    </div>
</template>
