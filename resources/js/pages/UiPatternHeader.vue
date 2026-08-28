<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildHeader } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const logoText = ref('サイト名');
const navLinksText = ref('ホーム\nツール一覧\nお問い合わせ');
const backgroundColor = ref('#ffffff');
const shadowEnabled = ref(true);
const isMobilePreview = ref(false);
const navOpen = ref(false);

const navLinks = computed(() =>
    navLinksText.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0),
);

const output = computed(() =>
    buildHeader({
        logoText: logoText.value,
        navLinks: navLinks.value,
        backgroundColor: backgroundColor.value,
        shadowEnabled: shadowEnabled.value,
    }),
);

const headerStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    boxShadow: shadowEnabled.value ? '0 1px 3px rgba(0, 0, 0, 0.1)' : 'none',
}));

const copiedHtml = ref(false);
const copiedCss = ref(false);
const copiedJs = ref(false);

async function copyText(text: string, flag: 'html' | 'css' | 'js') {
    try {
        await navigator.clipboard.writeText(text);

        if (flag === 'html') {
            copiedHtml.value = true;
            setTimeout(() => {
                copiedHtml.value = false;
            }, 1500);
        } else if (flag === 'css') {
            copiedCss.value = true;
            setTimeout(() => {
                copiedCss.value = false;
            }, 1500);
        } else {
            copiedJs.value = true;
            setTimeout(() => {
                copiedJs.value = false;
            }, 1500);
        }
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([logoText, navLinksText, backgroundColor, shadowEnabled], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-header/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ヘッダー - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="ヘッダー"
                description="値を調整すると、モバイル用トグル付きヘッダーのHTML/CSS/JSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm text-gray-700">プレビュー</span>
                    <label
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input v-model="isMobilePreview" type="checkbox" />
                        モバイル表示をシミュレート
                    </label>
                </div>

                <div
                    class="mb-8 overflow-hidden rounded-md border border-gray-200"
                >
                    <div
                        class="flex items-center justify-between px-6 py-4"
                        :style="headerStyle"
                    >
                        <span class="font-bold">{{ logoText }}</span>
                        <button
                            v-if="isMobilePreview"
                            type="button"
                            class="flex w-6 flex-col gap-1"
                            @click="navOpen = !navOpen"
                        >
                            <span class="block h-0.5 bg-current"></span>
                            <span class="block h-0.5 bg-current"></span>
                            <span class="block h-0.5 bg-current"></span>
                        </button>
                        <nav v-else class="flex gap-6 text-sm">
                            <a v-for="link in navLinks" :key="link" href="#">{{
                                link
                            }}</a>
                        </nav>
                    </div>
                    <nav
                        v-if="isMobilePreview && navOpen"
                        class="flex flex-col gap-3 px-6 pb-4 text-sm"
                        :style="headerStyle"
                    >
                        <a v-for="link in navLinks" :key="link" href="#">{{
                            link
                        }}</a>
                    </nav>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700">
                        ロゴテキスト
                        <input
                            v-model="logoText"
                            type="text"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        背景色
                        <input
                            v-model="backgroundColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700 sm:col-span-2">
                        ナビリンク(1行に1つ)
                        <textarea
                            v-model="navLinksText"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        ></textarea>
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input v-model="shadowEnabled" type="checkbox" />
                        影を付ける(box-shadow)
                    </label>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >HTML</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.html, 'html')"
                        >
                            {{ copiedHtml ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.html }}</pre>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >CSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.css, 'css')"
                        >
                            {{ copiedCss ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.css }}</pre>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >JS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.js, 'js')"
                        >
                            {{ copiedJs ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.js }}</pre>
                </div>

                <section v-if="props.patterns.length > 0" class="mt-16">
                    <h2 class="text-lg font-bold text-gray-900">
                        いろんなヘッダー
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        気に入ったものをそのままコピーして使えます。
                    </p>
                    <div
                        class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <PatternCard
                            v-for="pattern in props.patterns"
                            :key="pattern.id"
                            :id="pattern.id"
                            :title="pattern.title"
                            :locked="pattern.locked"
                            :html="pattern.html"
                            :css="pattern.css"
                            :locked-srcdoc="pattern.srcdoc"
                        />
                    </div>
                </section>
            </PageMain>
        </div>
    </PublicLayout>
</template>
