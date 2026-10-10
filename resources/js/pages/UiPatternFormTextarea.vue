<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildFormTextarea } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const placeholder = ref('コメントを入力してください');
const rows = ref(4);
const borderRadius = ref(6);
const borderColor = ref('#d1d5db');
const disabled = ref(false);

const output = computed(() =>
    buildFormTextarea({
        placeholder: placeholder.value,
        rows: rows.value,
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
    fontFamily: 'inherit',
    resize: 'vertical' as const,
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

watch([placeholder, rows, borderRadius, borderColor, disabled], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-form-textarea/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="フォームテキストエリア - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="フォームテキストエリア"
                description="値を調整すると、複数行テキスト入力欄のHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex h-40 items-center rounded-md border border-gray-200 bg-white px-6"
                >
                    <textarea
                        :rows="rows"
                        :placeholder="placeholder"
                        :disabled="disabled"
                        :style="previewStyle"
                        class="disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700">
                        プレースホルダー
                        <input
                            v-model="placeholder"
                            type="text"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        行数: {{ rows }}
                        <input
                            v-model.number="rows"
                            type="range"
                            min="2"
                            max="10"
                            class="mt-1 w-full"
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
                    <label class="text-sm text-gray-700">
                        ボーダー色
                        <input
                            v-model="borderColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input v-model="disabled" type="checkbox" />
                        無効化(disabled)
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

                <section v-if="props.patterns.length > 0" class="mt-16">
                    <h2 class="text-lg font-bold text-gray-900">
                        いろんなフォーム(テキストエリア)
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        気に入ったものをそのままコピーして使えます。
                    </p>
                    <div
                        class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3"
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
