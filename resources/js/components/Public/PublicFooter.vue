<script setup lang="ts">
import { computed } from 'vue';

const currentYear = computed(() => new Date().getFullYear());

interface NavLink {
    label: string;
    href: string;
    external?: boolean;
}

interface NavGroup {
    heading: string;
    links: NavLink[];
}

const NAV_GROUPS: NavGroup[] = [
    {
        heading: 'ツール集',
        links: [
            { label: 'About', href: '/about' },
            { label: 'Service', href: '/service' },
            { label: '料金案内', href: '/premium' },
            { label: 'よくある質問', href: '/faq' },
        ],
    },
    {
        heading: '関連サービス',
        links: [
            {
                label: 'かつコード',
                href: 'https://katsucode.jp/',
                external: true,
            },
            {
                label: 'katsulog',
                href: 'https://katsu-coach.com/',
                external: true,
            },
            {
                label: 'SmartSprouts',
                href: 'https://smartsprouts.jp/',
                external: true,
            },
        ],
    },
    {
        heading: '規約・ポリシー',
        links: [
            { label: 'プライバシーポリシー', href: '/legal/privacy-policy' },
            { label: '利用規約', href: '/legal/terms' },
            { label: 'クッキーポリシー', href: '/legal/cookie-policy' },
            {
                label: '特定商取引法に基づく表記',
                href: '/legal/tokushoho',
            },
        ],
    },
    {
        heading: '会社情報',
        links: [
            { label: '会社案内', href: '/about' },
            { label: 'お問い合わせ', href: '/contact' },
            { label: 'ブログ・お知らせ', href: '/blog' },
        ],
    },
];
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                <div v-for="group in NAV_GROUPS" :key="group.heading">
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-gray-400"
                    >
                        {{ group.heading }}
                    </p>
                    <nav class="mt-3 flex flex-col gap-2">
                        <a
                            v-for="link in group.links"
                            :key="link.href"
                            :href="link.href"
                            :target="link.external ? '_blank' : undefined"
                            :rel="
                                link.external
                                    ? 'noopener noreferrer'
                                    : undefined
                            "
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                </div>
            </div>

            <p class="mt-10 text-sm text-gray-400">
                &copy; {{ currentYear }} katsuooool
            </p>
        </div>
    </footer>
</template>
