<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';

interface NavItem {
    label: string;
    routeName: string;
    activePattern?: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: 'ダッシュボード', routeName: 'admin.dashboard' },
    {
        label: 'User管理',
        routeName: 'admin.users.index',
        activePattern: 'admin.users.*',
    },
    {
        label: '投稿管理',
        routeName: 'admin.posts.index',
        activePattern: 'admin.posts.*',
    },
];

function logout() {
    router.post(route('admin.logout'));
}
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <aside
            class="flex w-56 shrink-0 flex-col border-r border-gray-200 bg-white"
        >
            <div class="px-6 py-6">
                <span class="text-lg font-bold text-gray-900">Admin</span>
            </div>

            <nav class="flex flex-1 flex-col gap-1 px-3">
                <Link
                    v-for="item in NAV_ITEMS"
                    :key="item.routeName"
                    :href="route(item.routeName)"
                    class="rounded-md px-3 py-2 text-sm transition"
                    :class="
                        route().current(item.activePattern ?? item.routeName)
                            ? 'bg-gray-900 text-white'
                            : 'text-gray-700 hover:bg-gray-100'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="px-3 pb-6">
                <button
                    type="button"
                    class="w-full rounded-md px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    @click="logout"
                >
                    ログアウト
                </button>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header
                v-if="$slots.header"
                class="border-b border-gray-200 bg-white px-8 py-6"
            >
                <slot name="header" />
            </header>

            <main class="px-8 py-10">
                <slot />
            </main>
        </div>
    </div>
</template>
