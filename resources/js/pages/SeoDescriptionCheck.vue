<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { checkDescriptionLength } from '@/lib/seoTextCheck';

const input = ref('');

const result = computed(() => checkDescriptionLength(input.value));

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(input, (value) => {
    if (value.trim() === '') {
        return;
    }

    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/seo-description-check/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="description文字数チェック - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="description文字数チェック"
                description="meta descriptionの文字列を貼り付けると、文字数・バイト数と表示可否目安を確認できます。"
            />

            <PageMain>
                <label
                    for="description-input"
                    class="block text-sm font-medium text-ink-900/70"
                    >description</label
                >
                <textarea
                    id="description-input"
                    v-model="input"
                    rows="4"
                    class="mt-1 w-full rounded-md border border-ink-900/20 px-3 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                    placeholder="ページのmeta descriptionを入力"
                />

                <div class="mt-4 flex gap-6 text-sm text-ink-900/70">
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
                            ? '推奨範囲(160字程度)に収まっています。'
                            : '検索結果で末尾が省略される可能性があります(目安: モバイル120字/デスクトップ160字程度)。'
                    }}
                </p>
            </PageMain>
        </div>
    </PublicLayout>
</template>
