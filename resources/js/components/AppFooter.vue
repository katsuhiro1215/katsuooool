<script setup lang="ts">
const CONTENT_LINKS = [
    { label: 'About', href: '/about' },
    { label: 'Service', href: '/service' },
    { label: 'よくある質問', href: '/faq' },
    { label: 'お知らせ', href: '/blog' },
    { label: 'お問い合わせ', href: '/contact' },
];

const LEGAL_LINKS = [
    { label: '特定商取引法に基づく表記', href: '/legal/tokushoho' },
    { label: 'プライバシーポリシー', href: '/legal/privacy-policy' },
    { label: '利用規約', href: '/legal/terms' },
    { label: 'クッキーポリシー', href: '/legal/cookie-policy' },
];
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                class="flex flex-col gap-4 text-sm text-gray-500 sm:flex-row sm:items-start sm:justify-between"
            >
                <p>&copy; katsuooool</p>

                <div class="flex flex-col gap-3 sm:flex-row sm:gap-8">
                    <nav class="flex flex-wrap gap-x-4 gap-y-2">
                        <a
                            v-for="link in CONTENT_LINKS"
                            :key="link.href"
                            :href="link.href"
                            class="hover:text-gray-700"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                    <nav class="flex flex-wrap gap-x-4 gap-y-2">
                        <a
                            v-for="link in LEGAL_LINKS"
                            :key="link.href"
                            :href="link.href"
                            class="hover:text-gray-700"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>
</template>
