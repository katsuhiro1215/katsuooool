<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildButton } from '@/lib/uiPatternGenerators';
import type { ButtonSize } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const backgroundColor = ref('#2563eb');
const textColor = ref('#ffffff');
const size = ref<ButtonSize>('medium');
const borderRadius = ref(6);
const borderEnabled = ref(false);
const borderColor = ref('#000000');

const output = computed(() =>
    buildButton({
        backgroundColor: backgroundColor.value,
        textColor: textColor.value,
        size: size.value,
        borderRadius: borderRadius.value,
        borderEnabled: borderEnabled.value,
        borderColor: borderColor.value,
    }),
);

const SIZE_STYLES: Record<ButtonSize, { padding: string; fontSize: string }> = {
    small: { padding: '6px 12px', fontSize: '14px' },
    medium: { padding: '10px 20px', fontSize: '16px' },
    large: { padding: '14px 28px', fontSize: '18px' },
};

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    color: textColor.value,
    padding: SIZE_STYLES[size.value].padding,
    fontSize: SIZE_STYLES[size.value].fontSize,
    borderRadius: `${borderRadius.value}px`,
    border: borderEnabled.value ? `1px solid ${borderColor.value}` : 'none',
    cursor: 'pointer',
}));

const copiedHtml = ref(false);
const copiedCss = ref(false);

async function copyHtml() {
    try {
        await navigator.clipboard.writeText(output.value.html);
        copiedHtml.value = true;
        setTimeout(() => {
            copiedHtml.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

async function copyCss() {
    try {
        await navigator.clipboard.writeText(output.value.css);
        copiedCss.value = true;
        setTimeout(() => {
            copiedCss.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    [
        backgroundColor,
        textColor,
        size,
        borderRadius,
        borderEnabled,
        borderColor,
    ],
    () => {
        if (trackTimeout) {
            clearTimeout(trackTimeout);
        }

        trackTimeout = setTimeout(() => {
            axios.post('/ui-pattern-button/track').catch(() => {
                // Analytics-only endpoint; ignore failures.
            });
        }, 500);
    },
);
</script>

<template>
    <Head title="ボタン - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="ボタン"
                description="値を調整すると、ボタンのHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex h-32 items-center justify-center rounded-md border border-gray-200 bg-white"
                >
                    <button type="button" :style="previewStyle">ボタン</button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700">
                        背景色
                        <input
                            v-model="backgroundColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        文字色
                        <input
                            v-model="textColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        サイズ
                        <select
                            v-model="size"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="small">小</option>
                            <option value="medium">中</option>
                            <option value="large">大</option>
                        </select>
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
                    <label
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input v-model="borderEnabled" type="checkbox" />
                        ボーダーを表示する
                    </label>
                    <label class="text-sm text-gray-700">
                        ボーダー色
                        <input
                            v-model="borderColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                            :disabled="!borderEnabled"
                        />
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
                            @click="copyHtml"
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
                            @click="copyCss"
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
                        いろんなボタン
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
            </PageMain>
        </div>
    </PublicLayout>
</template>
