<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildGlassmorphism } from '@/lib/cssGenerators';

const opacity = ref(0.2);
const blur = ref(10);
const borderOpacity = ref(0.3);
const shadowOpacity = ref(0.37);
const copied = ref(false);

const css = computed(() =>
    buildGlassmorphism({
        opacity: opacity.value,
        blur: blur.value,
        borderOpacity: borderOpacity.value,
        shadowOpacity: shadowOpacity.value,
    }),
);

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

watch([opacity, blur, borderOpacity, shadowOpacity], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-glassmorphism/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Glassmorphism ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Glassmorphism ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ガラス風カードのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            <div
                class="bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%23e5e7eb%22/%3E%3Crect x=%2240%22 y=%2240%22 width=%2240%22 height=%2240%22 fill=%22%23e5e7eb%22/%3E%3C/svg%3E')] mb-8 flex h-40 items-center justify-center rounded-md bg-[length:20px_20px]"
            >
                <div class="h-24 w-40 rounded-lg" :style="css"></div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    透明度: {{ opacity }}
                    <input
                        v-model.number="opacity"
                        type="range"
                        min="0"
                        max="1"
                        step="0.05"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    ぼかし: {{ blur }}px
                    <input
                        v-model.number="blur"
                        type="range"
                        min="0"
                        max="40"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    境界線の透明度: {{ borderOpacity }}
                    <input
                        v-model.number="borderOpacity"
                        type="range"
                        min="0"
                        max="1"
                        step="0.05"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    影の強さ: {{ shadowOpacity }}
                    <input
                        v-model.number="shadowOpacity"
                        type="range"
                        min="0"
                        max="1"
                        step="0.01"
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
