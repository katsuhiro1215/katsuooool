<script setup lang="ts">
const LEGAL_LINKS = [
    { label: '特定商取引法に基づく表記', href: '/legal/tokushoho' },
    { label: 'プライバシーポリシー', href: '/legal/privacy-policy' },
    { label: '利用規約', href: '/legal/terms' },
    { label: 'クッキーポリシー', href: '/legal/cookie-policy' },
];
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-8 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between"
        >
            <p>&copy; katsuooool</p>
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
    </footer>
</template>
