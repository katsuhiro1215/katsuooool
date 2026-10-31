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

interface SocialLink {
    label: string;
    href: string;
}

const SOCIAL_LINKS: SocialLink[] = [
    { label: 'Facebook', href: 'https://www.facebook.com/katsucode20220513' },
    { label: 'Instagram', href: 'https://www.instagram.com/katsucode/' },
    { label: 'X', href: 'https://x.com/KatsuhiroKakoi' },
];

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
            { label: '会社案内', href: '/company' },
            { label: 'お問い合わせ', href: '/contact' },
            { label: 'ブログ・お知らせ', href: '/blog' },
        ],
    },
];
</script>

<template>
    <footer class="border-t border-ink-900/10 bg-white">
        <div class="mx-auto px-6 py-10">
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                <div v-for="group in NAV_GROUPS" :key="group.heading">
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-ink-900/35"
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
                            class="text-sm text-ink-900/60 hover:text-ink-900"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                </div>
            </div>

            <div
                class="mt-10 flex items-center justify-between border-t border-ink-900/10 pt-6"
            >
                <div class="flex items-center gap-4">
                    <a
                        v-for="social in SOCIAL_LINKS"
                        :key="social.href"
                        :href="social.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="social.label"
                        class="text-ink-900/35 transition hover:text-gold-600"
                    >
                        <svg
                            v-if="social.label === 'Facebook'"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.91h-2.34V22c4.78-.76 8.44-4.92 8.44-9.94z"
                            />
                        </svg>
                        <svg
                            v-else-if="social.label === 'Instagram'"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.22.6 1.77 1.15.5.5.9 1.11 1.15 1.77.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 01-1.15 1.77 4.9 4.9 0 01-1.77 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 01-1.77-1.15 4.9 4.9 0 01-1.15-1.77c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.22 1.15-1.77A4.9 4.9 0 015.45.53c.64-.25 1.37-.42 2.43-.47C8.94.01 9.28 0 12 0zm0 5a5 5 0 100 10 5 5 0 000-10zm0 8.25a3.25 3.25 0 110-6.5 3.25 3.25 0 010 6.5zm5.2-8.45a1.17 1.17 0 100-2.34 1.17 1.17 0 000 2.34z"
                                transform="translate(0 2)"
                            />
                        </svg>
                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M18.9 2H22l-7.6 8.7L23.3 22h-7.2l-5.6-6.9L4 22H1l8.1-9.3L1 2h7.3l5 6.3L18.9 2zm-1.3 18h1.9L7.5 4H5.5l12.1 16z"
                            />
                        </svg>
                    </a>
                </div>

                <p class="text-sm text-ink-900/35">
                    &copy; {{ currentYear }} katsuooool
                </p>
            </div>
        </div>
    </footer>
</template>
