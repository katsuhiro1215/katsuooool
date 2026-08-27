<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { contrastRatio, parseHex } from '@/lib/color';

const foreground = ref('#1f2937');
const background = ref('#ffffff');

const ratio = computed(() => {
    const fg = parseHex(foreground.value);
    const bg = parseHex(background.value);

    if (!fg || !bg) {
        return null;
    }

    return contrastRatio(fg, bg);
});

interface Criterion {
    label: string;
    threshold: number;
}

const CRITERIA: Criterion[] = [
    { label: '通常テキスト AA', threshold: 4.5 },
    { label: '通常テキスト AAA', threshold: 7 },
    { label: '大きな文字 AA', threshold: 3 },
    { label: '大きな文字 AAA', threshold: 4.5 },
];

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/color-contrast/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}

watch([foreground, background], () => {
    if (ratio.value !== null) {
        track();
    }
});
</script>

<template>
    <Head title="コントラストチェック - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    コントラストチェック
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    文字色と背景色を選択すると、WCAG
                    2.1のコントラスト比とAA/AAA適合を判定します。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            <div class="flex flex-wrap gap-6">
                <div>
                    <label
                        for="foreground"
                        class="block text-sm font-medium text-gray-700"
                        >文字色</label
                    >
                    <input
                        id="foreground"
                        v-model="foreground"
                        type="color"
                        class="mt-1 h-12 w-24 cursor-pointer rounded-md border border-gray-200"
                    />
                </div>
                <div>
                    <label
                        for="background"
                        class="block text-sm font-medium text-gray-700"
                        >背景色</label
                    >
                    <input
                        id="background"
                        v-model="background"
                        type="color"
                        class="mt-1 h-12 w-24 cursor-pointer rounded-md border border-gray-200"
                    />
                </div>
            </div>

            <div
                v-if="ratio !== null"
                class="mt-6 rounded-lg border border-gray-200 bg-white p-6"
            >
                <p
                    class="flex h-24 items-center justify-center rounded-md text-lg font-semibold"
                    :style="{ backgroundColor: background, color: foreground }"
                >
                    サンプルテキスト Sample Text
                </p>

                <p class="mt-4 text-2xl font-bold text-gray-900">
                    {{ ratio.toFixed(2) }}:1
                </p>

                <ul class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <li
                        v-for="criterion in CRITERIA"
                        :key="criterion.label"
                        class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2"
                    >
                        <span class="text-gray-700">{{ criterion.label }}</span>
                        <span
                            :class="
                                ratio >= criterion.threshold
                                    ? 'text-green-600'
                                    : 'text-red-600'
                            "
                            class="font-medium"
                        >
                            {{
                                ratio >= criterion.threshold ? '合格' : '不合格'
                            }}
                        </span>
                    </li>
                </ul>
            </div>
        </main>
    </div>
</template>
