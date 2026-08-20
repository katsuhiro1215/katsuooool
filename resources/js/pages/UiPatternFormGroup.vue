<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildFormGroup } from '@/lib/uiPatternGenerators';

const label = ref('メールアドレス');
const placeholder = ref('you@example.com');
const helperText = ref('登録用のメールアドレスを入力してください');
const hasError = ref(false);

const output = computed(() =>
    buildFormGroup({
        label: label.value,
        placeholder: placeholder.value,
        helperText: helperText.value,
        hasError: hasError.value,
    }),
);

const inputStyle = computed(() => ({
    display: 'block',
    width: '100%',
    padding: '8px 12px',
    fontSize: '14px',
    border: `1px solid ${hasError.value ? '#dc2626' : '#d1d5db'}`,
    borderRadius: '6px',
    boxSizing: 'border-box' as const,
}));

const helperStyle = computed(() => ({
    margin: '0',
    fontSize: '12px',
    color: hasError.value ? '#dc2626' : '#666666',
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

watch([label, placeholder, helperText, hasError], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-form-group/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="フォームグループ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    フォームグループ
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ラベル・入力欄・ヘルパーテキストを組み合わせたフォーム項目のHTML/CSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 flex h-40 flex-col justify-center gap-1 rounded-md border border-gray-200 bg-white px-6"
            >
                <label class="text-sm font-bold">{{ label }}</label>
                <input
                    type="text"
                    :placeholder="placeholder"
                    :style="inputStyle"
                />
                <p :style="helperStyle">{{ helperText }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    ラベル
                    <input
                        v-model="label"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    プレースホルダー
                    <input
                        v-model="placeholder"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    />
                </label>
                <label class="text-sm text-gray-700 sm:col-span-2">
                    ヘルパーテキスト
                    <input
                        v-model="helperText"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    />
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="hasError" type="checkbox" />
                    エラー状態(is-invalid)
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
