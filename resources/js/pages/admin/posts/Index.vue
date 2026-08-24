<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface PostRow {
    id: number;
    title: string;
    slug: string;
    type: string;
    published_at: string | null;
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

defineProps<{ posts: Paginated<PostRow> }>();

const TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

function statusLabel(publishedAt: string | null): string {
    if (publishedAt === null) {
        return '下書き';
    }

    return new Date(publishedAt) <= new Date() ? '公開中' : '予約公開';
}
</script>

<template>
    <Head title="投稿管理 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">投稿管理</h1>
                <Link
                    :href="route('admin.posts.create')"
                    class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    新規作成
                </Link>
            </div>
        </template>

        <div v-if="posts.data.length === 0" class="text-sm text-gray-500">
            投稿がありません。
        </div>

        <div
            v-else
            class="overflow-hidden rounded-lg border border-gray-200 bg-white"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-gray-200 bg-gray-50 text-gray-500"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">タイトル</th>
                        <th class="px-4 py-3 font-medium">種別</th>
                        <th class="px-4 py-3 font-medium">状態</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="post in posts.data"
                        :key="post.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="route('admin.posts.edit', post.id)"
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ post.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ TYPE_LABELS[post.type] }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ statusLabel(post.published_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="posts.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in posts.links"
                :key="link.label"
                :href="link.url ?? ''"
                :class="[
                    'rounded-md px-3 py-1.5 text-sm',
                    link.active
                        ? 'bg-gray-900 text-white'
                        : 'text-gray-700 hover:bg-gray-100',
                    link.url === null && 'pointer-events-none text-gray-300',
                ]"
            >
                <span v-html="link.label"></span>
            </Link>
        </nav>
    </AdminLayout>
</template>
