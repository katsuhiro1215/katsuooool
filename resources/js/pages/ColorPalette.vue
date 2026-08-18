<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

import { randomHex } from '@/lib/color';

const SWATCH_COUNT = 5;

interface Swatch {
    hex: string;
    locked: boolean;
}

const swatches = ref<Swatch[]>(
    Array.from({ length: SWATCH_COUNT }, () => ({
        hex: randomHex(),
        locked: false,
    })),
);
const copiedHex = ref('');

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/color-palette/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}

function generate() {
    swatches.value = swatches.value.map((swatch) =>
        swatch.locked ? swatch : { ...swatch, hex: randomHex() },
    );

    track();
}

function toggleLock(index: number) {
    swatches.value[index].locked = !swatches.value[index].locked;
}

async function copy(hex: string) {
    try {
        await navigator.clipboard.writeText(hex);
        copiedHex.value = hex;
        setTimeout(() => {
            if (copiedHex.value === hex) {
                copiedHex.value = '';
            }
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}
</script>

<template>
    <Head title="カラーパレット生成 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    カラーパレット生成
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    ランダムな5色のパレットを生成します。気に入った色はロックして再生成できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <button
                type="button"
                class="mb-6 w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700"
                @click="generate"
            >
                生成する
            </button>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                <div
                    v-for="(swatch, index) in swatches"
                    :key="index"
                    class="overflow-hidden rounded-lg border border-gray-200"
                >
                    <button
                        type="button"
                        class="h-32 w-full"
                        :style="{ backgroundColor: swatch.hex }"
                        :aria-label="`${swatch.hex} をコピー`"
                        @click="copy(swatch.hex)"
                    />
                    <div
                        class="flex items-center justify-between bg-white px-3 py-2"
                    >
                        <span class="font-mono text-xs text-gray-600">
                            {{
                                copiedHex === swatch.hex
                                    ? 'コピーしました'
                                    : swatch.hex
                            }}
                        </span>
                        <button
                            type="button"
                            class="text-xs"
                            :class="
                                swatch.locked
                                    ? 'text-gray-900'
                                    : 'text-gray-300 hover:text-gray-500'
                            "
                            :aria-pressed="swatch.locked"
                            :aria-label="
                                swatch.locked ? 'ロック解除' : 'ロック'
                            "
                            @click="toggleLock(index)"
                        >
                            {{ swatch.locked ? '🔒' : '🔓' }}
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
