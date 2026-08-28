<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { buildWavePattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const waveHeight = ref(10);
const wavelength = ref(100);
const copied = ref(false);

const css = computed(() =>
    buildWavePattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        waveHeight: waveHeight.value,
        wavelength: wavelength.value,
    }),
);

// The preview reuses the same generated CSS string directly: unlike the
// gradient-based tools, the wave pattern's background-image is a single
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

watch([backgroundColor, patternColor, waveHeight, wavelength], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-wave/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="波模様パターン - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="波模様パターン"
                description="値を調整すると、波模様の背景パターンのCSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
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
                        波の高さ: {{ waveHeight }}px
                        <input
                            v-model.number="waveHeight"
                            type="range"
                            min="5"
                            max="30"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        波長: {{ wavelength }}px
                        <input
                            v-model.number="wavelength"
                            type="range"
                            min="20"
                            max="200"
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
            </PageMain>
        </div>
    </PublicLayout>
</template>
