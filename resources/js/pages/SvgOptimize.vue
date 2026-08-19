<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import { optimizeSvgWithStats } from '@/lib/svg';

const input = ref('');
const result = ref<ReturnType<typeof optimizeSvgWithStats> | null>(null);
const copied = ref(false);

const canOptimize = computed(() => input.value.trim() !== '');

function optimize() {
    if (!canOptimize.value) {
        return;
    }

    result.value = optimizeSvgWithStats(input.value);
    track();
}

function onFileInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';

    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        input.value = String(reader.result ?? '');
        result.value = null;
    };
    reader.readAsText(file);
}

async function copyOutput() {
    if (!result.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(result.value.output);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the output is still visible as text.
    }
}

function downloadOutput() {
    if (!result.value) {
        return;
    }

    const blob = new Blob([result.value.output], { type: 'image/svg+xml' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'optimized.svg';
    link.click();
    URL.revokeObjectURL(url);
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/svg-optimize/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}
</script>

<template>
    <Head title="SVG最適化 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">SVG最適化</h1>
                <p class="mt-2 text-sm text-gray-600">
                    SVGコードを貼り付けるかファイルを選択すると、コメント・不要な空白・エディタ由来のメタデータを除去します。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="flex items-center justify-between">
                <label
                    for="svg-input"
                    class="block text-sm font-medium text-gray-700"
                    >SVGコード</label
                >
                <label
                    class="cursor-pointer text-sm text-gray-600 underline hover:text-gray-900"
                >
                    ファイルを選択
                    <input
                        type="file"
                        class="hidden"
                        accept=".svg,image/svg+xml"
                        @change="onFileInputChange"
                    />
                </label>
            </div>
            <textarea
                id="svg-input"
                v-model="input"
                rows="10"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs focus:border-gray-400 focus:outline-none focus:ring-0"
                placeholder="<svg>...</svg>"
            />

            <button
                type="button"
                class="mt-4 w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                :disabled="!canOptimize"
                @click="optimize"
            >
                最適化する
            </button>

            <div v-if="result" class="mt-8">
                <div
                    class="mb-3 flex items-center justify-between text-sm text-gray-600"
                >
                    <span
                        >{{ result.originalBytes }} B →
                        {{ result.optimizedBytes }} B({{
                            result.reductionPercent
                        }}%削減)</span
                    >
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:border-gray-400"
                            @click="copyOutput"
                        >
                            {{ copied ? 'コピーしました' : 'コピー' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:border-gray-400"
                            @click="downloadOutput"
                        >
                            ダウンロード
                        </button>
                    </div>
                </div>
                <textarea
                    :value="result.output"
                    rows="10"
                    readonly
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 font-mono text-xs text-gray-800"
                />
            </div>
        </main>
    </div>
</template>
