<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { buildGridPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const lineColor = ref('#000000');
const lineWidth = ref(1);
const cellSize = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildGridPattern({
        backgroundColor: backgroundColor.value,
        lineColor: lineColor.value,
        lineWidth: lineWidth.value,
        cellSize: cellSize.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `linear-gradient(to right, ${lineColor.value} ${lineWidth.value}px, transparent ${lineWidth.value}px), linear-gradient(to bottom, ${lineColor.value} ${lineWidth.value}px, transparent ${lineWidth.value}px)`,
    backgroundSize: `${cellSize.value}px ${cellSize.value}px`,
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

watch([backgroundColor, lineColor, lineWidth, cellSize], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-grid/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="グリッドパターン - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="グリッドパターン"
                description="値を調整すると、格子柄の背景パターンのCSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 h-48 rounded-md border border-ink-900/10"
                    :style="previewStyle"
                ></div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-ink-900/70">
                        背景色
                        <input
                            v-model="backgroundColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        パターン色(線の色)
                        <input
                            v-model="lineColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        線の太さ: {{ lineWidth }}px
                        <input
                            v-model.number="lineWidth"
                            type="range"
                            min="1"
                            max="5"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        マス目サイズ: {{ cellSize }}px
                        <input
                            v-model.number="cellSize"
                            type="range"
                            min="10"
                            max="100"
                            class="mt-1 w-full"
                        />
                    </label>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-ink-900/70"
                            >生成されたCSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-ink-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-ink-700"
                            @click="copy"
                        >
                            {{ copied ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-ink-900 p-4 text-xs text-white/90"
                        >{{ css }}</pre>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
