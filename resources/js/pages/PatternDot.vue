<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildDotPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const radius = ref(3);
const spacing = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildDotPattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        radius: radius.value,
        spacing: spacing.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `radial-gradient(circle, ${patternColor.value} ${radius.value}px, transparent ${radius.value}px)`,
    backgroundSize: `${spacing.value}px ${spacing.value}px`,
}));

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

watch([backgroundColor, patternColor, radius, spacing], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-dot/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ドットパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    ドットパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ドット柄の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
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
                    ドットサイズ: {{ radius }}px
                    <input
                        v-model.number="radius"
                        type="range"
                        min="1"
                        max="20"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    間隔: {{ spacing }}px
                    <input
                        v-model.number="spacing"
                        type="range"
                        min="10"
                        max="100"
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
