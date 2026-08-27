<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PatternCard from '@/components/tools/PatternCard.vue';
import { buildFormSelect } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const optionsText = ref('東京都\n大阪府\n愛知県');
const borderRadius = ref(6);
const borderColor = ref('#d1d5db');
const disabled = ref(false);

const options = computed(() =>
    optionsText.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0),
);

const output = computed(() =>
    buildFormSelect({
        options: options.value,
        borderRadius: borderRadius.value,
        borderColor: borderColor.value,
        disabled: disabled.value,
    }),
);

const previewStyle = computed(() => ({
    display: 'block',
    width: '100%',
    padding: '8px 12px',
    fontSize: '14px',
    backgroundColor: '#ffffff',
    border: `1px solid ${borderColor.value}`,
    borderRadius: `${borderRadius.value}px`,
    boxSizing: 'border-box' as const,
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

watch([optionsText, borderRadius, borderColor, disabled], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-form-select/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="セレクトボックス - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    セレクトボックス
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ドロップダウン選択欄のHTML/CSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            <div
                class="mb-8 flex h-40 items-center rounded-md border border-gray-200 bg-white px-6"
            >
                <select
                    :disabled="disabled"
                    :style="previewStyle"
                    class="disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
                >
                    <option v-for="option in options" :key="option">
                        {{ option }}
                    </option>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 sm:col-span-2">
                    選択肢(1行に1つ)
                    <textarea
                        v-model="optionsText"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    ></textarea>
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
                <label class="text-sm text-gray-700">
                    ボーダー色
                    <input
                        v-model="borderColor"
                        type="color"
                        class="mt-1 h-9 w-full"
                    />
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="disabled" type="checkbox" />
                    無効化(disabled)
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

            <section v-if="props.patterns.length > 0" class="mt-16">
                <h2 class="text-lg font-bold text-gray-900">
                    いろんなフォーム(セレクト)
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
                        :title="pattern.title"
                        :locked="pattern.locked"
                        :html="pattern.html"
                        :css="pattern.css"
                        :locked-srcdoc="pattern.srcdoc"
                    />
                </div>
            </section>
        </main>
    </div>
</template>
