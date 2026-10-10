<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';

import AppButton from '@/components/tools/AppButton.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { buildToast } from '@/lib/uiPatternGenerators';
import type { ToastVariant } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const VARIANT_OPTIONS: { key: ToastVariant; label: string; color: string }[] = [
    { key: 'success', label: 'Success', color: '#16a34a' },
    { key: 'error', label: 'Error', color: '#dc2626' },
    { key: 'info', label: 'Info', color: '#2563eb' },
    { key: 'warning', label: 'Warning', color: '#d97706' },
];

const variant = ref<ToastVariant>('success');
const message = ref('保存しました。');
const duration = ref(3);

const output = computed(() =>
    buildToast({
        variant: variant.value,
        message: message.value,
        duration: duration.value,
    }),
);

const variantColor = computed(
    () => VARIANT_OPTIONS.find((option) => option.key === variant.value)!.color,
);

const isPreviewVisible = ref(false);
let previewTimeout: ReturnType<typeof setTimeout> | null = null;

function showPreview() {
    isPreviewVisible.value = true;

    if (previewTimeout) {
        clearTimeout(previewTimeout);
    }

    previewTimeout = setTimeout(() => {
        isPreviewVisible.value = false;
    }, duration.value * 1000);
}

const copiedHtml = ref(false);
const copiedCss = ref(false);
const copiedJs = ref(false);

async function copyText(text: string, flag: 'html' | 'css' | 'js') {
    try {
        await navigator.clipboard.writeText(text);

        if (flag === 'html') {
            copiedHtml.value = true;
            setTimeout(() => {
                copiedHtml.value = false;
            }, 1500);
        } else if (flag === 'css') {
            copiedCss.value = true;
            setTimeout(() => {
                copiedCss.value = false;
            }, 1500);
        } else {
            copiedJs.value = true;
            setTimeout(() => {
                copiedJs.value = false;
            }, 1500);
        }
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([variant, message, duration], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-toast/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="トースト通知 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="トースト通知"
                description="値を調整すると、トースト通知のHTML/CSS/JSコードがリアルタイムで生成されます。「表示する」ボタンで実際の表示・自動消滅を確認できます。"
            />

            <PageMain>
                <div
                    class="relative mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white"
                >
                    <AppButton type="button" @click="showPreview">
                        表示する
                    </AppButton>

                    <div
                        v-if="isPreviewVisible"
                        class="absolute bottom-4 right-4 flex items-center gap-3 rounded-md px-4 py-3 text-sm text-white"
                        :style="{ backgroundColor: variantColor }"
                    >
                        <span>{{ message }}</span>
                        <button
                            type="button"
                            class="text-base text-white"
                            @click="isPreviewVisible = false"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700 sm:col-span-2">
                        バリエーション
                        <div class="mt-1 flex gap-4">
                            <label
                                v-for="option in VARIANT_OPTIONS"
                                :key="option.key"
                                class="flex items-center gap-1 text-sm"
                            >
                                <input
                                    v-model="variant"
                                    type="radio"
                                    :value="option.key"
                                />
                                {{ option.label }}
                            </label>
                        </div>
                    </label>
                    <label class="text-sm text-gray-700">
                        メッセージ
                        <input
                            v-model="message"
                            type="text"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        表示時間: {{ duration }}秒
                        <input
                            v-model.number="duration"
                            type="range"
                            min="1"
                            max="10"
                            class="mt-1 w-full"
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

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >JS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.js, 'js')"
                        >
                            {{ copiedJs ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.js }}</pre>
                </div>

                <section v-if="props.patterns.length > 0" class="mt-16">
                    <h2 class="text-lg font-bold text-gray-900">
                        いろんなトースト
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
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
