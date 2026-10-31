<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildFooter } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const copyrightText = ref('© 2026 katsuooool');
const linksText = ref('利用規約\nプライバシーポリシー');
const backgroundColor = ref('#f5f5f5');
const isMobilePreview = ref(false);

const links = computed(() =>
    linksText.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0),
);

const output = computed(() =>
    buildFooter({
        copyrightText: copyrightText.value,
        links: links.value,
        backgroundColor: backgroundColor.value,
    }),
);

const footerStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
}));

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

watch([copyrightText, linksText, backgroundColor], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-footer/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="フッター - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="フッター"
                description="値を調整すると、レスポンシブ対応のフッターのHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm text-ink-900/70">プレビュー</span>
                    <label
                        class="flex items-center gap-2 text-sm text-ink-900/70"
                    >
                        <input v-model="isMobilePreview" type="checkbox" />
                        モバイル表示をシミュレート
                    </label>
                </div>

                <div
                    class="mb-8 overflow-hidden rounded-md border border-ink-900/10"
                >
                    <div
                        class="flex items-center justify-between px-6 py-6"
                        :class="{
                            'flex-col gap-3 text-center': isMobilePreview,
                        }"
                        :style="footerStyle"
                    >
                        <nav
                            class="flex gap-4 text-sm"
                            :class="{ 'flex-col gap-2': isMobilePreview }"
                        >
                            <a v-for="link in links" :key="link" href="#">{{
                                link
                            }}</a>
                        </nav>
                        <p class="text-xs opacity-70">{{ copyrightText }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-ink-900/70">
                        コピーライトテキスト
                        <input
                            v-model="copyrightText"
                            type="text"
                            class="mt-1 w-full rounded-md border border-ink-900/20 px-2 py-1.5 text-sm"
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
                    <label class="text-sm text-ink-900/70 sm:col-span-2">
                        リンク(1行に1つ)
                        <textarea
                            v-model="linksText"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-ink-900/20 px-2 py-1.5 text-sm"
                        ></textarea>
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
                            @click="copyText(output.html, 'html')"
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
                            @click="copyText(output.css, 'css')"
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
                        いろんなフッター
                    </h2>
                    <p class="mt-1 text-sm text-ink-900/60">
                        気に入ったものをそのままコピーして使えます。
                    </p>
                    <div
                        class="mt-6 grid grid-cols-1 gap-6"
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
