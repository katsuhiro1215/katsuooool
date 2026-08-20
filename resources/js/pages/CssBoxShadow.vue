<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildBoxShadow } from '@/lib/cssGenerators';

const x = ref(4);
const y = ref(8);
const blur = ref(16);
const spread = ref(0);
const color = ref('#000000');
const inset = ref(false);
const copied = ref(false);

const css = computed(() =>
    buildBoxShadow({
        x: x.value,
        y: y.value,
        blur: blur.value,
        spread: spread.value,
        color: color.value,
        inset: inset.value,
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

watch([x, y, blur, spread, color, inset], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-box-shadow/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Box Shadow ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Box Shadow ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、box-shadowのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white"
            >
                <div class="h-20 w-20 rounded-md bg-white" :style="css"></div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    X オフセット: {{ x }}px
                    <input
                        v-model.number="x"
                        type="range"
                        min="-50"
                        max="50"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    Y オフセット: {{ y }}px
                    <input
                        v-model.number="y"
                        type="range"
                        min="-50"
                        max="50"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    ぼかし: {{ blur }}px
                    <input
                        v-model.number="blur"
                        type="range"
                        min="0"
                        max="100"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    広がり: {{ spread }}px
                    <input
                        v-model.number="spread"
                        type="range"
                        min="-50"
                        max="50"
                        class="mt-1 w-full"
                    />
                </label>
                <label class="text-sm text-gray-700">
                    色
                    <input
                        v-model="color"
                        type="color"
                        class="mt-1 h-9 w-full"
                    />
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="inset" type="checkbox" />
                    inset(内側に影)
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
