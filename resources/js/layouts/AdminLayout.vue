<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ref } from 'vue';

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
    {
        label: 'UIパターン集管理',
        routeName: 'admin.ui-pattern-snippets.index',
        activePattern: 'admin.ui-pattern-snippets.*',
    },
];

const showUserMenu = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);

onClickOutside(userMenuRef, () => {
    showUserMenu.value = false;
});

function logout() {
    router.post(route('admin.logout'));
}
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <aside
            class="flex w-56 shrink-0 flex-col border-r border-gray-200 bg-white"
        >
            <div class="border-b border-gray-200 px-3 py-4">
                <div class="flex items-center gap-2">
                    <svg
                        class="h-6 w-6 text-gray-900"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"
                        />
                    </svg>
                    <div>
                        <div class="text-sm font-bold text-gray-900">
                            katsuooool
                        </div>
                        <div class="text-xs text-gray-500">Admin</div>
                    </div>
                </div>
            </div>

            <nav class="flex flex-1 flex-col gap-1 px-3 py-4">
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
        </aside>

        <div class="min-w-0 flex-1">
            <header class="border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between px-8 py-4">
                    <Link
                        href="/"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>
                        公開サイトへ
                    </Link>

                    <div ref="userMenuRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-full focus:outline-none focus:ring-2 focus:ring-gray-300"
                            @click="showUserMenu = !showUserMenu"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-sm font-medium text-gray-700"
                            >
                                A
                            </div>
                        </button>

                        <div
                            v-if="showUserMenu"
                            class="absolute right-0 mt-2 w-48 origin-top-right rounded-md border border-gray-200 bg-white shadow-lg"
                        >
                            <div class="py-1">
                                <button
                                    type="button"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                    @click="logout"
                                >
                                    ログアウト
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex min-h-screen flex-1 flex-col bg-gray-50">
                <div v-if="$slots.header" class="px-8 py-4">
                    <slot name="header" />
                </div>
                <div class="px-8 py-10">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
