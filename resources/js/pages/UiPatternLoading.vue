<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PatternCard from '@/components/tools/PatternCard.vue';
import { buildLoading } from '@/lib/uiPatternGenerators';
import type { LoadingType } from '@/lib/uiPatternGenerators';

interface PatternSnippet {
    id: number;
    title: string;
    html: string;
    css: string;
}

const props = defineProps<{ patterns: PatternSnippet[] }>();

const TYPE_OPTIONS: { key: LoadingType; label: string }[] = [
    { key: 'spinner', label: 'スピナー' },
    { key: 'dots', label: 'ドット' },
    { key: 'bar', label: 'バー' },
];

const type = ref<LoadingType>('spinner');
const color = ref('#2563eb');
const size = ref(40);

const output = computed(() =>
    buildLoading({
        type: type.value,
        color: color.value,
        size: size.value,
    }),
);

const spinnerStyle = computed(() => ({
    width: `${size.value}px`,
    height: `${size.value}px`,
    borderWidth: `${Math.max(2, Math.round(size.value / 10))}px`,
    borderStyle: 'solid',
    borderColor: '#e5e5e5',
    borderTopColor: color.value,
    borderRadius: '50%',
}));

const dotSize = computed(() => Math.round(size.value / 4));

function dotStyle(index: number) {
    return {
        width: `${dotSize.value}px`,
        height: `${dotSize.value}px`,
        backgroundColor: color.value,
        animationDelay: `${index * 0.15}s`,
    };
}

const barTrackStyle = computed(() => ({
    width: `${size.value * 4}px`,
    height: '6px',
    backgroundColor: '#e5e5e5',
    borderRadius: '3px',
    overflow: 'hidden' as const,
}));

const barFillStyle = computed(() => ({
    width: '40%',
    height: '100%',
    backgroundColor: color.value,
    borderRadius: '3px',
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

watch([type, color, size], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-loading/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ローディング - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    ローディング
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ローディングインジケーターのHTML/CSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            <div
                class="mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white"
            >
                <div
                    v-if="type === 'spinner'"
                    class="loading-spinner-preview"
                    :style="spinnerStyle"
                ></div>
                <div v-else-if="type === 'dots'" class="flex gap-1.5">
                    <span
                        v-for="i in 3"
                        :key="i"
                        class="loading-dot-preview inline-block rounded-full"
                        :style="dotStyle(i - 1)"
                    ></span>
                </div>
                <div v-else :style="barTrackStyle">
                    <div
                        class="loading-bar-fill-preview"
                        :style="barFillStyle"
                    ></div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="text-sm text-gray-700">
                    タイプ
                    <select
                        v-model="type"
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    >
                        <option
                            v-for="option in TYPE_OPTIONS"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <label class="text-sm text-gray-700">
                    色
                    <input
                        v-model="color"
                        type="color"
                        class="mt-1 h-9 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    サイズ: {{ size }}px
                    <input
                        v-model.number="size"
                        type="range"
                        min="16"
                        max="80"
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

            <section v-if="props.patterns.length > 0" class="mt-16">
                <h2 class="text-lg font-bold text-gray-900">
                    いろんなローディング
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
                        :html="pattern.html"
                        :css="pattern.css"
                    />
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.loading-spinner-preview {
    animation: loading-spin 0.8s linear infinite;
}

.loading-dot-preview {
    animation: loading-bounce 0.6s ease-in-out infinite;
}

.loading-bar-fill-preview {
    animation: loading-bar-move 1.2s ease-in-out infinite;
}

@keyframes loading-spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes loading-bounce {
    0%,
    80%,
    100% {
        transform: scale(0.6);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes loading-bar-move {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(350%);
    }
}
</style>
