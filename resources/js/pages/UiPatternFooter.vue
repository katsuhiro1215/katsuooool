<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildFooter } from '@/lib/uiPatternGenerators';

const copyrightText = ref('© 2026 katsuooool');
const linksText = ref('利用規約\nプライバシーポリシー');
const backgroundColor = ref('#f5f5f5');
const isMobilePreview = ref(false);

const links = computed(() =>
    linksText.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0),
);

const output = computed(() =>
    buildFooter({
        copyrightText: copyrightText.value,
        links: links.value,
        backgroundColor: backgroundColor.value,
    }),
);

const footerStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
}));

const copiedHtml = ref(false);
const copiedCss = ref(false);

async function copyText(text: string, flag: 'html' | 'css') {
    try {
        await navigator.clipboard.writeText(text);

        if (flag === 'html') {
            copiedHtml.value = true;
            setTimeout(() => {
                copiedHtml.value = false;
            }, 1500);
        } else {
            copiedCss.value = true;
            setTimeout(() => {
                copiedCss.value = false;
            }, 1500);
        }
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([copyrightText, linksText, backgroundColor], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-footer/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="フッター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">フッター</h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、レスポンシブ対応のフッターのHTML/CSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="mb-4 flex items-center justify-between">
                <span class="text-sm text-gray-700">プレビュー</span>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="isMobilePreview" type="checkbox" />
                    モバイル表示をシミュレート
                </label>
            </div>

            <div class="mb-8 overflow-hidden rounded-md border border-gray-200">
                <div
                    class="flex items-center justify-between px-6 py-6"
                    :class="{ 'flex-col gap-3 text-center': isMobilePreview }"
                    :style="footerStyle"
                >
                    <nav
                        class="flex gap-4 text-sm"
                        :class="{ 'flex-col gap-2': isMobilePreview }"
                    >
                        <a v-for="link in links" :key="link" href="#">{{
                            link
                        }}</a>
                    </nav>
                    <p class="text-xs opacity-70">{{ copyrightText }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    コピーライトテキスト
                    <input
                        v-model="copyrightText"
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
                    リンク(1行に1つ)
                    <textarea
                        v-model="linksText"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    ></textarea>
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">HTML</span>
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
                    <span class="text-sm font-medium text-gray-700">CSS</span>
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
        </main>
    </div>
</template>
