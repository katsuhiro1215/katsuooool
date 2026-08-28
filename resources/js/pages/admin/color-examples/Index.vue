<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const TYPE_LABELS: Record<string, string> = {
    palette: 'パレット',
    gradient: 'グラデーション',
    scheme: '色の組み合わせ',
};

interface ExampleRow {
    id: number;
    type: string;
    title: string;
    copy_count: number;
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

defineProps<{ examples: Paginated<ExampleRow> }>();
</script>

<template>
    <Head title="カラー例管理 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">カラー例管理</h1>
                <Link
                    :href="route('admin.color-examples.create')"
                    class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    新規作成
                </Link>
            </div>
        </template>

        <div v-if="examples.data.length === 0" class="text-sm text-gray-500">
            カラー例がありません。
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
                        <th class="px-4 py-3 font-medium">コピー回数</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="example in examples.data"
                        :key="example.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    route(
                                        'admin.color-examples.edit',
                                        example.id,
                                    )
                                "
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ example.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ TYPE_LABELS[example.type] }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ example.copy_count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="examples.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in examples.links"
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
