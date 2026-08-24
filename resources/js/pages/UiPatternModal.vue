<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';
import { buildModal } from '@/lib/uiPatternGenerators';

const title = ref('タイトル');
const text = ref('本文テキストがここに入ります。');
const backgroundColor = ref('#ffffff');
const borderRadius = ref(8);
const overlayOpacity = ref(0.5);

const output = computed(() =>
    buildModal({
        title: title.value,
        text: text.value,
        backgroundColor: backgroundColor.value,
        borderRadius: borderRadius.value,
        overlayOpacity: overlayOpacity.value,
    }),
);

const isPreviewOpen = ref(false);

const overlayStyle = computed(() => ({
    backgroundColor: `rgba(0, 0, 0, ${overlayOpacity.value})`,
}));

const modalStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    borderRadius: `${borderRadius.value}px`,
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

watch([title, text, backgroundColor, borderRadius, overlayOpacity], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-modal/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="モーダル - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">モーダル</h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、モーダルのHTML/CSS/JSコードがリアルタイムで生成されます。「開く」ボタンで実際の開閉動作を確認できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="relative mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white"
            >
                <AppButton type="button" @click="isPreviewOpen = true">
                    開く
                </AppButton>

                <div
                    v-if="isPreviewOpen"
                    class="absolute inset-0 flex items-center justify-center rounded-md"
                    :style="overlayStyle"
                >
                    <div class="w-64 p-6" :style="modalStyle">
                        <h3 class="mb-3 text-lg font-bold">{{ title }}</h3>
                        <p class="mb-4 text-sm text-gray-600">{{ text }}</p>
                        <button
                            type="button"
                            class="rounded bg-gray-200 px-4 py-2 text-sm"
                            @click="isPreviewOpen = false"
                        >
                            閉じる
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    タイトル
                    <input
                        v-model="title"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    本文
                    <input
                        v-model="text"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
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
                <label class="text-sm text-gray-700">
                    角丸: {{ borderRadius }}px
                    <input
                        v-model.number="borderRadius"
                        type="range"
                        min="0"
                        max="24"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700 sm:col-span-2">
                    オーバーレイの不透明度: {{ overlayOpacity }}
                    <input
                        v-model.number="overlayOpacity"
                        type="range"
                        min="0"
                        max="1"
                        step="0.05"
                        class="mt-1 w-full"
                    />
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

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">JS</span>
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
        </main>
    </div>
</template>
