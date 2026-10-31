<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { buildStripePattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const stripeWidth = ref(10);
const angle = ref(45);
const copied = ref(false);

const css = computed(() =>
    buildStripePattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        stripeWidth: stripeWidth.value,
        angle: angle.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `repeating-linear-gradient(${angle.value}deg, ${patternColor.value} 0px ${stripeWidth.value}px, ${backgroundColor.value} ${stripeWidth.value}px ${stripeWidth.value * 2}px)`,
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

watch([backgroundColor, patternColor, stripeWidth, angle], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-stripe/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ストライプパターン - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="ストライプパターン"
                description="値を調整すると、ストライプ柄の背景パターンのCSSコードがリアルタイムで生成されます。"
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
                        パターン色
                        <input
                            v-model="patternColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        ストライプ幅: {{ stripeWidth }}px
                        <input
                            v-model.number="stripeWidth"
                            type="range"
                            min="2"
                            max="50"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        角度: {{ angle }}度
                        <input
                            v-model.number="angle"
                            type="range"
                            min="0"
                            max="180"
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
