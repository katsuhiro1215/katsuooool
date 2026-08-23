<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface UserDetail {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    email_verified: boolean;
    two_factor_enabled: boolean;
    passkey_count: number;
}

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

defineProps<{
    user: UserDetail;
    toolEvents: Paginated<ToolEventRow>;
}>();

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString('ja-JP') : '-';
}
</script>

<template>
    <Head :title="`${user.name} - User管理 - Admin`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.users.index')"
                    class="text-sm text-gray-500 hover:text-gray-700"
                    >← User一覧に戻る</Link
                >
            </div>
            <h1 class="mt-1 text-lg font-bold text-gray-900">
                {{ user.name }}
            </h1>
        </template>

        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">メールアドレス</p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ user.email }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">登録日</p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ formatDate(user.created_at) }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">メール認証</p>
                <p
                    class="mt-1 text-sm font-medium"
                    :class="
                        user.email_verified ? 'text-green-600' : 'text-gray-400'
                    "
                >
                    {{ user.email_verified ? '認証済み' : '未認証' }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">2FA / パスキー</p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ user.two_factor_enabled ? '2FA有効' : '2FA無効' }} ・
                    パスキー{{ user.passkey_count }}件
                </p>
            </div>
        </div>

        <h2 class="mb-4 text-sm font-medium text-gray-700">ツール利用履歴</h2>

        <div v-if="toolEvents.data.length === 0" class="text-sm text-gray-500">
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
                    link.url === null && 'pointer-events-none text-gray-300',
                ]"
            >
                <span v-html="link.label"></span>
            </Link>
        </nav>
    </AdminLayout>
</template>
