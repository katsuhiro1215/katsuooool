<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildSkeleton } from '@/lib/uiPatternGenerators';
import type { SkeletonType } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const TYPE_OPTIONS: { key: SkeletonType; label: string }[] = [
    { key: 'text', label: 'テキスト行' },
    { key: 'avatar', label: 'アバター行' },
    { key: 'card', label: 'カード型' },
];

const type = ref<SkeletonType>('text');
const lines = ref(3);
const color = ref('#e5e5e5');

const output = computed(() =>
    buildSkeleton({
        type: type.value,
        lines: lines.value,
        color: color.value,
    }),
);

const skeletonBlockStyle = computed(() => ({
    backgroundColor: color.value,
    backgroundImage:
        'linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.5) 50%, transparent 100%)',
    backgroundSize: '200% 100%',
    animation: 'skeleton-shimmer-preview 1.5s ease-in-out infinite',
}));

const lineCount = computed(() => Math.max(1, lines.value));

function lineStyle(index: number) {
    return {
        ...skeletonBlockStyle.value,
        width:
            index === lineCount.value - 1 && lineCount.value > 1
                ? '60%'
                : '100%',
    };
}

const copiedHtml = ref(false);
const copiedCss = ref(false);

async function copyText(text: string, flag: 'html' | 'css') {
    try {
        await navigator.clipboard.writeText(text);

        if (flag === 'html') {
            copiedHtml.value = true;
            setTimeout(() => {
                copiedHtml.value = false;
            }, 1500);
        } else {
            copiedCss.value = true;
            setTimeout(() => {
                copiedCss.value = false;
            }, 1500);
        }
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([type, lines, color], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-skeleton/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="スケルトンローディング - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="スケルトンローディング"
                description="値を調整すると、スケルトンローディングのHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex h-40 items-center rounded-md border border-gray-200 bg-white px-6"
                >
                    <div
                        v-if="type === 'text'"
                        class="flex w-full flex-col gap-2"
                    >
                        <div
                            v-for="i in lineCount"
                            :key="i"
                            class="h-3 rounded"
                            :style="lineStyle(i - 1)"
                        ></div>
                    </div>
                    <div
                        v-else-if="type === 'avatar'"
                        class="flex w-full items-center gap-3"
                    >
                        <div
                            class="h-12 w-12 shrink-0 rounded-full"
                            :style="skeletonBlockStyle"
                        ></div>
                        <div class="flex flex-1 flex-col gap-2">
                            <div
                                v-for="i in lineCount"
                                :key="i"
                                class="h-3 rounded"
                                :style="lineStyle(i - 1)"
                            ></div>
                        </div>
                    </div>
                    <div v-else class="flex w-full flex-col gap-3">
                        <div
                            class="h-20 w-full rounded-lg"
                            :style="skeletonBlockStyle"
                        ></div>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="i in lineCount"
                                :key="i"
                                class="h-3 rounded"
                                :style="lineStyle(i - 1)"
                            ></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <label class="text-sm text-gray-700">
                        タイプ
                        <select
                            v-model="type"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option
                                v-for="option in TYPE_OPTIONS"
                                :key="option.key"
                                :value="option.key"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700">
                        行数: {{ lines }}
                        <input
                            v-model.number="lines"
                            type="range"
                            min="1"
                            max="5"
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
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >HTML</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.html, 'html')"
                        >
                            {{ copiedHtml ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.html }}</pre>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >CSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.css, 'css')"
                        >
                            {{ copiedCss ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.css }}</pre>
                </div>

                <section v-if="props.patterns.length > 0" class="mt-16">
                    <h2 class="text-lg font-bold text-gray-900">
                        いろんなスケルトン
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        気に入ったものをそのままコピーして使えます。
                    </p>
                    <div
                        class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <PatternCard
                            v-for="pattern in props.patterns"
                            :key="pattern.id"
                            :title="pattern.title"
                            :locked="pattern.locked"
                            :html="pattern.html"
                            :css="pattern.css"
                            :locked-srcdoc="pattern.srcdoc"
                        />
                    </div>
                </section>
            </PageMain>
        </div>
    </PublicLayout>
</template>

<style scoped>
@keyframes skeleton-shimmer-preview {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>
