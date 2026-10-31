<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildCard } from '@/lib/uiPatternGenerators';
import type { CardVariant } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const variant = ref<CardVariant>('plain');
const backgroundColor = ref('#ffffff');
const borderRadius = ref(8);
const shadowEnabled = ref(true);
const padding = ref(16);
const title = ref('タイトル');
const text = ref('本文テキストがここに入ります。');

const output = computed(() =>
    buildCard({
        variant: variant.value,
        backgroundColor: backgroundColor.value,
        borderRadius: borderRadius.value,
        shadowEnabled: shadowEnabled.value,
        padding: padding.value,
        title: title.value,
        text: text.value,
    }),
);

const cardStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    borderRadius: `${borderRadius.value}px`,
    boxShadow: shadowEnabled.value ? '0 1px 3px rgba(0, 0, 0, 0.1)' : 'none',
    overflow: variant.value === 'image' ? ('hidden' as const) : undefined,
    padding: variant.value === 'plain' ? `${padding.value}px` : undefined,
}));

const bodyStyle = computed(() => ({
    padding: `${padding.value}px`,
}));

const copiedHtml = ref(false);
const copiedCss = ref(false);

async function copyHtml() {
    try {
        await navigator.clipboard.writeText(output.value.html);
        copiedHtml.value = true;
        setTimeout(() => {
            copiedHtml.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

async function copyCss() {
    try {
        await navigator.clipboard.writeText(output.value.css);
        copiedCss.value = true;
        setTimeout(() => {
            copiedCss.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    [
        variant,
        backgroundColor,
        borderRadius,
        shadowEnabled,
        padding,
        title,
        text,
    ],
    () => {
        if (trackTimeout) {
            clearTimeout(trackTimeout);
        }

        trackTimeout = setTimeout(() => {
            axios.post('/ui-pattern-card/track').catch(() => {
                // Analytics-only endpoint; ignore failures.
            });
        }, 500);
    },
);
</script>

<template>
    <Head title="カード - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="カード"
                description="値を調整すると、カードのHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex items-center justify-center rounded-md border border-ink-900/10 bg-white p-8"
                >
                    <div class="w-64" :style="cardStyle">
                        <img
                            v-if="variant === 'image'"
                            src="https://placehold.co/400x200"
                            alt=""
                            class="block h-40 w-full object-cover"
                        />
                        <div
                            :style="variant === 'image' ? bodyStyle : undefined"
                        >
                            <h3 class="mb-2 text-lg font-bold">{{ title }}</h3>
                            <p class="text-sm text-ink-900/60">{{ text }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-ink-900/70 sm:col-span-2">
                        バリエーション
                        <div class="mt-1 flex gap-4">
                            <label class="flex items-center gap-1 text-sm">
                                <input
                                    v-model="variant"
                                    type="radio"
                                    value="plain"
                                />
                                通常
                            </label>
                            <label class="flex items-center gap-1 text-sm">
                                <input
                                    v-model="variant"
                                    type="radio"
                                    value="image"
                                />
                                画像付き
                            </label>
                        </div>
                    </label>
                    <label class="text-sm text-ink-900/70">
                        タイトル
                        <input
                            v-model="title"
                            type="text"
                            class="mt-1 w-full rounded-md border border-ink-900/20 px-3 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        本文
                        <input
                            v-model="text"
                            type="text"
                            class="mt-1 w-full rounded-md border border-ink-900/20 px-3 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        背景色
                        <input
                            v-model="backgroundColor"
                            type="color"
                            class="mt-1 h-9 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        角丸: {{ borderRadius }}px
                        <input
                            v-model.number="borderRadius"
                            type="range"
                            min="0"
                            max="24"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        パディング: {{ padding }}px
                        <input
                            v-model.number="padding"
                            type="range"
                            min="8"
                            max="32"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-ink-900/70"
                    >
                        <input v-model="shadowEnabled" type="checkbox" />
                        影を表示する
                    </label>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-ink-900/70"
                            >HTML</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-ink-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-ink-700"
                            @click="copyHtml"
                        >
                            {{ copiedHtml ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-ink-900 p-4 text-xs text-white/90"
                        >{{ output.html }}</pre>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-ink-900/70"
                            >CSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-ink-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-ink-700"
                            @click="copyCss"
                        >
                            {{ copiedCss ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-ink-900 p-4 text-xs text-white/90"
                        >{{ output.css }}</pre>
                </div>

                <section v-if="props.patterns.length > 0" class="mt-16">
                    <h2 class="text-lg font-bold text-ink-900">
                        いろんなカード
                    </h2>
                    <p class="mt-1 text-sm text-ink-900/60">
                        気に入ったものをそのままコピーして使えます。
                    </p>
                    <div
                        class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <PatternCard
                            v-for="pattern in props.patterns"
                            :key="pattern.id"
                            :id="pattern.id"
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
