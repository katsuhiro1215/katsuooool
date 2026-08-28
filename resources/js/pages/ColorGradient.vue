<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import ColorExampleGallery from '@/components/tools/ColorExampleGallery.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

interface ColorExampleData {
    id: number;
    title: string;
    colors: string[];
    angle: number | null;
    copy_count: number;
}

const props = defineProps<{ examples: ColorExampleData[] }>();

const startColor = ref('#3366ff');
const endColor = ref('#ff3366');
const angle = ref(90);

const cssGradient = computed(
    () =>
        `linear-gradient(${angle.value}deg, ${startColor.value}, ${endColor.value})`,
);
const cssCode = computed(() => `background: ${cssGradient.value};`);
const copied = ref(false);

async function copyCode() {
    try {
        await navigator.clipboard.writeText(cssCode.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the code is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/color-gradient/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}

watch([startColor, endColor, angle], track);
</script>

<template>
    <Head title="グラデーション生成 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="グラデーション生成"
                description="開始色・終了色・角度を指定して、CSSの線形グラデーションを生成します。"
            />

            <PageMain>
                <div class="flex flex-wrap items-end gap-6">
                    <div>
                        <label
                            for="start"
                            class="block text-sm font-medium text-gray-700"
                            >開始色</label
                        >
                        <input
                            id="start"
                            v-model="startColor"
                            type="color"
                            class="mt-1 h-12 w-24 cursor-pointer rounded-md border border-gray-200"
                        />
                    </div>
                    <div>
                        <label
                            for="end"
                            class="block text-sm font-medium text-gray-700"
                            >終了色</label
                        >
                        <input
                            id="end"
                            v-model="endColor"
                            type="color"
                            class="mt-1 h-12 w-24 cursor-pointer rounded-md border border-gray-200"
                        />
                    </div>
                    <div class="flex-1">
                        <label
                            for="angle"
                            class="block text-sm font-medium text-gray-700"
                            >角度({{ angle }}度)</label
                        >
                        <input
                            id="angle"
                            v-model.number="angle"
                            type="range"
                            min="0"
                            max="360"
                            class="mt-3 w-full"
                        />
                    </div>
                </div>

                <div
                    class="mt-6 h-40 rounded-lg border border-gray-200"
                    :style="{ backgroundImage: cssGradient }"
                />

                <div class="mt-4 flex items-center gap-2">
                    <code
                        class="flex-1 rounded-md border border-gray-200 bg-white px-3 py-2 font-mono text-sm text-gray-800"
                        >{{ cssCode }}</code
                    >
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:border-gray-400"
                        @click="copyCode"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>

                <ColorExampleGallery :examples="props.examples" type="gradient" />
            </PageMain>
        </div>
    </PublicLayout>
</template>
