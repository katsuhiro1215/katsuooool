<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildBorderRadius } from '@/lib/cssGenerators';

const topLeft = ref(16);
const topRight = ref(16);
const bottomRight = ref(16);
const bottomLeft = ref(16);
const copied = ref(false);

const css = computed(() =>
    buildBorderRadius({
        topLeft: topLeft.value,
        topRight: topRight.value,
        bottomRight: bottomRight.value,
        bottomLeft: bottomLeft.value,
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

watch([topLeft, topRight, bottomRight, bottomLeft], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-border-radius/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Border Radius ジェネレーター - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Border Radius ジェネレーター"
                description="4隅を個別に調整すると、border-radiusのCSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white"
                >
                    <div class="h-24 w-24 bg-gray-900" :style="css"></div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700">
                        左上: {{ topLeft }}px
                        <input
                            v-model.number="topLeft"
                            type="range"
                            min="0"
                            max="200"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        右上: {{ topRight }}px
                        <input
                            v-model.number="topRight"
                            type="range"
                            min="0"
                            max="200"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        右下: {{ bottomRight }}px
                        <input
                            v-model.number="bottomRight"
                            type="range"
                            min="0"
                            max="200"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        左下: {{ bottomLeft }}px
                        <input
                            v-model.number="bottomLeft"
                            type="range"
                            min="0"
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
