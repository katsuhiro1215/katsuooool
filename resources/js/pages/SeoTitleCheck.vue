<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { checkTitleLength } from '@/lib/seoTextCheck';

const input = ref('');

const result = computed(() => checkTitleLength(input.value));

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(input, (value) => {
    if (value.trim() === '') {
        return;
    }

    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/seo-title-check/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="title文字数チェック - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="title文字数チェック"
                description="titleタグの文字列を貼り付けると、文字数・バイト数と検索結果での表示可否目安を確認できます。"
            />

            <PageMain>
                <label
                    for="title-input"
                    class="block text-sm font-medium text-gray-700"
                    >title</label
                >
                <textarea
                    id="title-input"
                    v-model="input"
                    rows="3"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    placeholder="ページのtitleを入力"
                />

                <div class="mt-4 flex gap-6 text-sm text-gray-700">
                    <span>文字数: {{ result.characterCount }}</span>
                    <span>バイト数: {{ result.byteCount }}</span>
                </div>

                <p
                    class="mt-2 text-sm"
                    :class="
                        result.withinRecommended
                            ? 'text-green-600'
                            : 'text-amber-600'
                    "
                >
                    {{
                        result.withinRecommended
                            ? '推奨範囲(全角30〜35字程度)に収まっています。'
                            : 'Google検索結果で末尾が省略される可能性があります(推奨: 全角30〜35字程度)。'
                    }}
                </p>
            </PageMain>
        </div>
    </PublicLayout>
</template>
