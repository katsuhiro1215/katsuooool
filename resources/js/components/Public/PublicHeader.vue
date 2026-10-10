<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import Dropdown from '@/components/Dropdown.vue';
import DropdownLink from '@/components/DropdownLink.vue';
import ResponsiveNavLink from '@/components/ResponsiveNavLink.vue';

defineProps<{
    user: { name: string } | null;
}>();

const showingMobileMenu = ref(false);
</script>

<template>
    <header class="border-b border-gray-200 bg-white">
        <div
            class="mx-auto flex items-center justify-between px-6 py-4"
        >
            <Link href="/" class="flex items-center gap-3">
                <ApplicationLogo class="h-8 w-auto" />
                <span class="text-lg font-bold text-gray-900">katsuooool</span>
                <span class="hidden text-sm text-gray-500 sm:inline"
                    >Web制作に役立つ実用ツール集</span
                >
            </Link>

            <!-- Desktop Navigation -->
            <div class="hidden items-center gap-4 md:flex">
                <Link
                    href="/premium"
                    class="group relative overflow-hidden rounded-full bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-400 px-4 py-1.5 text-sm font-semibold text-amber-950 shadow-sm transition hover:shadow-md hover:shadow-amber-300/50"
                >
                    <span class="relative z-10">Premium</span>
                    <span
                        class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 group-hover:translate-x-full"
                    ></span>
                </Link>

                <template v-if="user">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700"
                            >
                                {{ user.name }}
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('mypage.index')">
                                マイページ
                            </DropdownLink>
                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                ログアウト
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </template>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        ログイン
                    </Link>
                    <Link
                        :href="route('register')"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        新規登録
                    </Link>
                </template>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700"
                        >
                            その他
                            <svg
                                class="h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </template>
                    <template #content>
                        <a
                            href="https://katsucode.jp/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                        >
                            かつコード
                        </a>
                        <a
                            href="https://katsu-coach.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                        >
                            katsulog
                        </a>
                        <a
                            href="https://smartsprouts.jp/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                        >
                            SmartSprouts
                        </a>
                        <DropdownLink href="/contact">
                            お問い合わせ
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>

            <!-- Hamburger -->
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none md:hidden"
                :aria-expanded="showingMobileMenu"
                aria-label="メニューを開く"
                @click="showingMobileMenu = !showingMobileMenu"
            >
                <svg
                    class="h-6 w-6"
                    stroke="currentColor"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path
                        :class="{
                            hidden: showingMobileMenu,
                            'inline-flex': !showingMobileMenu,
                        }"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                    <path
                        :class="{
                            hidden: !showingMobileMenu,
                            'inline-flex': showingMobileMenu,
                        }"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </button>
        </div>

        <!-- Mobile Navigation -->
        <div
            :class="{ block: showingMobileMenu, hidden: !showingMobileMenu }"
            class="border-t border-gray-200 md:hidden"
        >
            <div class="space-y-1 py-3">
                <ResponsiveNavLink href="/premium"> Premium </ResponsiveNavLink>

                <template v-if="user">
                    <div
                        class="border-t border-gray-200 pt-2 text-sm font-medium text-gray-800"
                    >
                        <div class="px-4 py-1">{{ user.name }}</div>
                    </div>
                    <ResponsiveNavLink :href="route('mypage.index')">
                        マイページ
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        :href="route('logout')"
                        method="post"
                        as="button"
                    >
                        ログアウト
                    </ResponsiveNavLink>
                </template>
                <template v-else>
                    <ResponsiveNavLink :href="route('login')">
                        ログイン
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('register')">
                        新規登録
                    </ResponsiveNavLink>
                </template>

                <div class="border-t border-gray-200 pt-2">
                    <a
                        href="https://katsucode.jp/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block w-full border-l-4 border-transparent px-4 py-2 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800"
                    >
                        かつコード
                    </a>
                    <a
                        href="https://katsu-coach.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block w-full border-l-4 border-transparent px-4 py-2 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800"
                    >
                        katsulog
                    </a>
                    <a
                        href="https://smartsprouts.jp/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block w-full border-l-4 border-transparent px-4 py-2 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800"
                    >
                        SmartSprouts
                    </a>
                    <ResponsiveNavLink href="/contact">
                        お問い合わせ
                    </ResponsiveNavLink>
                </div>
            </div>
        </div>
    </header>
</template>
