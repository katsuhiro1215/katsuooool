<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

import { ref } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import ColorExampleGallery from '@/components/tools/ColorExampleGallery.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { randomHex } from '@/lib/color';

const SWATCH_COUNT = 5;

interface Swatch {
    hex: string;
    locked: boolean;
}

interface ColorExampleData {
    id: number;
    title: string;
    colors: string[];
    angle: number | null;
    copy_count: number;
}

const props = defineProps<{ examples: ColorExampleData[] }>();

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

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="カラーパレット生成"
                description="ランダムな5色のパレットを生成します。気に入った色はロックして再生成できます。"
            />

            <PageMain>
                <AppButton type="button" class="mb-6 w-full" @click="generate">
                    生成する
                </AppButton>

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

                <ColorExampleGallery :examples="props.examples" type="palette" />
            </PageMain>
        </div>
    </PublicLayout>
</template>
