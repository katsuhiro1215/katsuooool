<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { CATEGORY_LABELS } from '@/lib/uiPatternCategory';

interface SnippetRow {
    id: number;
    category: string;
    title: string;
    is_premium: boolean;
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

defineProps<{ snippets: Paginated<SnippetRow> }>();
</script>

<template>
    <Head title="UIパターン集管理 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">
                    UIパターン集管理
                </h1>
                <Link
                    :href="route('admin.ui-pattern-snippets.create')"
                    class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    新規作成
                </Link>
            </div>
        </template>

        <div v-if="snippets.data.length === 0" class="text-sm text-gray-500">
            パターンがありません。
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
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="snippet in snippets.data"
                        :key="snippet.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    route(
                                        'admin.ui-pattern-snippets.edit',
                                        snippet.id,
                                    )
                                "
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ snippet.title }}
                            </Link>
                            <span
                                v-if="snippet.is_premium"
                                class="ml-2 rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-bold text-white"
                            >
                                Premium
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ CATEGORY_LABELS[snippet.category] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="snippets.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in snippets.links"
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
