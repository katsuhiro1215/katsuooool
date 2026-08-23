<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    created_at: string;
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

const props = defineProps<{
    users: Paginated<UserRow>;
    search: string;
}>();

const query = ref(props.search);

function submitSearch() {
    router.get(
        route('admin.users.index'),
        { q: query.value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="User管理 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">User管理</h1>
        </template>

        <form class="mb-6 flex gap-2" @submit.prevent="submitSearch">
            <input
                v-model="query"
                type="text"
                placeholder="名前・メールアドレスで検索"
                class="w-full max-w-sm rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            />
            <button
                type="submit"
                class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
            >
                検索
            </button>
        </form>

        <div v-if="users.data.length === 0" class="text-sm text-gray-500">
            該当するUserがいません。
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
                        <th class="px-4 py-3 font-medium">名前</th>
                        <th class="px-4 py-3 font-medium">メールアドレス</th>
                        <th class="px-4 py-3 font-medium">登録日</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="route('admin.users.show', user.id)"
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ user.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{
                                new Date(user.created_at).toLocaleDateString(
                                    'ja-JP',
                                )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="users.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in users.links"
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
