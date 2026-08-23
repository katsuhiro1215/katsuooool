<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

interface ToolEventRow {
    id: number;
    tool_slug: string;
    event_type: string;
    created_at: string | null;
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

defineProps<{ toolEvents: Paginated<ToolEventRow> }>();

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString('ja-JP') : '-';
}

function openBillingPortal() {
    router.post(route('premium.billing-portal'));
}
</script>

<template>
    <Head title="マイページ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <div class="mt-2 flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900">マイページ</h1>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        @click="openBillingPortal"
                    >
                        請求ポータルを開く
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <h2 class="mb-4 text-sm font-medium text-gray-700">
                ツール利用履歴
            </h2>

            <div
                v-if="toolEvents.data.length === 0"
                class="text-sm text-gray-500"
            >
                利用履歴がありません。
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
                            <th class="px-4 py-3 font-medium">ツール</th>
                            <th class="px-4 py-3 font-medium">種別</th>
                            <th class="px-4 py-3 font-medium">日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in toolEvents.data"
                            :key="event.id"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <td class="px-4 py-3 text-gray-900">
                                {{ event.tool_slug }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ event.event_type }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ formatDate(event.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="toolEvents.links.length > 3"
                class="mt-4 flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in toolEvents.links"
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
    </div>
</template>
