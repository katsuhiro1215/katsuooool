<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import {
    buildPagination,
    computePaginationPages,
} from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const currentPage = ref(5);
const totalPages = ref(10);
const accentColor = ref('#2563eb');

const output = computed(() =>
    buildPagination({
        currentPage: currentPage.value,
        totalPages: totalPages.value,
        accentColor: accentColor.value,
    }),
);

const previewPages = computed(() =>
    computePaginationPages(currentPage.value, totalPages.value),
);

const isFirst = computed(() => currentPage.value <= 1);
const isLast = computed(() => currentPage.value >= totalPages.value);

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

watch([currentPage, totalPages, accentColor], () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }

    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-pagination/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ページネーション - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="ページネーション"
                description="値を調整すると、ページネーションのHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex items-center justify-center rounded-md border border-gray-200 bg-white p-8"
                >
                    <nav class="flex items-center gap-1">
                        <span
                            class="rounded px-3 py-1.5 text-sm"
                            :class="isFirst ? 'text-gray-300' : 'text-gray-700'"
                            >前へ</span
                        >
                        <template
                            v-for="(page, index) in previewPages"
                            :key="index"
                        >
                            <span
                                v-if="page === 'ellipsis'"
                                class="px-1 text-sm text-gray-400"
                                >…</span
                            >
                            <span
                                v-else
                                class="rounded px-3 py-1.5 text-sm"
                                :style="
                                    page === currentPage
                                        ? {
                                              backgroundColor: accentColor,
                                              color: '#ffffff',
                                          }
                                        : { color: '#333333' }
                                "
                                >{{ page }}</span
                            >
                        </template>
                        <span
                            class="rounded px-3 py-1.5 text-sm"
                            :class="isLast ? 'text-gray-300' : 'text-gray-700'"
                            >次へ</span
                        >
                    </nav>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <label class="text-sm text-gray-700">
                        現在ページ
                        <input
                            v-model.number="currentPage"
                            type="number"
                            min="1"
                            :max="totalPages"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        総ページ数
                        <input
                            v-model.number="totalPages"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                        />
                    </label>
                    <label class="text-sm text-gray-700">
                        アクセントカラー
                        <input
                            v-model="accentColor"
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
                            @click="copyHtml"
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
                            @click="copyCss"
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
                        いろんなページネーション
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
