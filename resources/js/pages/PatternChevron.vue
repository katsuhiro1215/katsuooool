<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildChevronPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const width = ref(40);
const height = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildChevronPattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        width: width.value,
        height: height.value,
    }),
);

// The preview reuses the same generated CSS string directly: unlike the
// gradient-based tools, the chevron pattern's background-image is a single
// SVG data URI, so there is no separate object-form property list to
// bind — the css string IS the complete, valid background shorthand set.
const previewStyle = computed(() => css.value);

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([backgroundColor, patternColor, width, height], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-chevron/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ジグザグパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    ジグザグパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、山形(シェブロン)柄の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

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
                    パターン色
                    <input
                        v-model="patternColor"
                        type="color"
                        class="mt-1 h-9 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    幅: {{ width }}px
                    <input
                        v-model.number="width"
                        type="range"
                        min="10"
                        max="100"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    高さ: {{ height }}px
                    <input
                        v-model.number="height"
                        type="range"
                        min="5"
                        max="50"
                        class="mt-1 w-full"
                    />
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700"
                        >生成されたCSS</span
                    >
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copy"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                    >{{ css }}</pre>
            </div>
        </main>
    </div>
</template>
