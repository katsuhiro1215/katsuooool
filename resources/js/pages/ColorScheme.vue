<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { rotateHue } from '@/lib/color';

const baseColor = ref('#3366ff');
const copiedHex = ref('');

interface SchemeDefinition {
    label: string;
    offsets: number[];
}

const SCHEMES: SchemeDefinition[] = [
    { label: '補色', offsets: [0, 180] },
    { label: '類似色', offsets: [0, 30, -30] },
    { label: '三色配色', offsets: [0, 120, 240] },
    { label: '分割補色', offsets: [0, 150, 210] },
    { label: '四色配色', offsets: [0, 90, 180, 270] },
];

const schemes = computed(() =>
    SCHEMES.map((scheme) => ({
        label: scheme.label,
        colors: scheme.offsets.map((offset) =>
            rotateHue(baseColor.value, offset),
        ),
    })),
);

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

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/color-scheme/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}

watch(baseColor, track);
</script>

<template>
    <Head title="色の組み合わせ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    色の組み合わせ
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    基準色を選択すると、補色・類似色・三色配色などの組み合わせを提案します。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div>
                <label
                    for="base"
                    class="block text-sm font-medium text-gray-700"
                    >基準色</label
                >
                <input
                    id="base"
                    v-model="baseColor"
                    type="color"
                    class="mt-1 h-12 w-24 cursor-pointer rounded-md border border-gray-200"
                />
            </div>

            <div class="mt-8 space-y-6">
                <div v-for="scheme in schemes" :key="scheme.label">
                    <h2 class="text-sm font-medium text-gray-700">
                        {{ scheme.label }}
                    </h2>
                    <div class="mt-2 flex gap-3">
                        <div
                            v-for="(hex, index) in scheme.colors"
                            :key="`${scheme.label}-${index}`"
                            class="flex flex-col items-center gap-1"
                        >
                            <button
                                type="button"
                                class="h-16 w-16 rounded-md border border-gray-200"
                                :style="{ backgroundColor: hex }"
                                :aria-label="`${hex} をコピー`"
                                @click="copy(hex)"
                            />
                            <span class="font-mono text-xs text-gray-600">
                                {{ copiedHex === hex ? 'コピーしました' : hex }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
