<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import BatchActionPanel from '@/components/image/BatchActionPanel.vue';
import ImageThumbGrid from '@/components/image/ImageThumbGrid.vue';
import ImageToolShell from '@/components/image/ImageToolShell.vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import { useImageBatch } from '@/composables/useImageBatch';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { isSuccess, summarize } from '@/lib/imageBatch';
import type { ThumbItem } from '@/lib/imageBatch';

const props = defineProps<{ maxFiles: number }>();

const ACCEPT =
    'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif';

const LEVELS = [
    { value: 'light', label: '控えめ', note: '画質そのまま（無劣化）' },
    {
        value: 'recommended',
        label: '推奨',
        note: '見た目はほぼ変わらず、小さくする',
    },
    { value: 'strong', label: '強め', note: 'より小さく。画質が少し落ちる' },
] as const;

const level = ref<'light' | 'recommended' | 'strong'>('recommended');

const {
    files,
    response,
    matched,
    phase,
    errorMessage,
    showPremiumUpsell,
    add,
    remove,
    clear,
    resetResults,
    submit,
} = useImageBatch({
    endpoint: '/image-compress',
    maxFiles: () => props.maxFiles,
    fallbackError: '圧縮処理に失敗しました。時間をおいて再度お試しください。',
});

const items = computed<ThumbItem[]>(() =>
    files.value.map((item, index) => {
        const result = matched.value[index] ?? null;
        let state: ThumbItem['state'] = 'ready';

        if (phase.value === 'processing') {
            state = 'processing';
        } else if (result !== null) {
            state = isSuccess(result) ? 'success' : 'failed';
        }

        return {
            id: item.id,
            name: item.file.name,
            size: item.file.size,
            previewUrl: item.previewUrl,
            state,
            result,
        };
    }),
);

const summary = computed(() =>
    response.value ? summarize(response.value.results) : null,
);
</script>

<template>
    <Head title="画像圧縮 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="画像圧縮"
                :description="`JPEG・PNG・WebP・HEIC画像を、見た目をほとんど変えずに軽くします。1ファイル10MBまで、最大${maxFiles}枚まで一度に処理できます。写真の向きや色情報とともに、位置情報(GPS)も残ります。`"
            />

            <PageMain>
                <ImageToolShell
                    :phase="phase"
                    :accept="ACCEPT"
                    :hint="`JPEG・PNG・WebP・HEIC ／ 1ファイル10MBまで ／ 最大${maxFiles}枚`"
                    @files="add"
                >
                    <template #grid>
                        <div
                            v-if="errorMessage"
                            class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                        >
                            <p>{{ errorMessage }}</p>
                            <Link
                                v-if="showPremiumUpsell"
                                href="/premium"
                                class="mt-1 inline-block underline"
                            >
                                Premiumについて詳しく見る
                            </Link>
                        </div>
                        <ImageThumbGrid
                            :items="items"
                            :can-add="
                                phase === 'ready' && files.length < maxFiles
                            "
                            :accept="ACCEPT"
                            @remove="remove"
                            @add="add"
                        />
                    </template>

                    <template #options>
                        <BatchActionPanel
                            :phase="phase === 'empty' ? 'ready' : phase"
                            :can-submit="files.length > 0"
                            submit-label="圧縮する"
                            processing-message="画像を圧縮しています。ファイルサイズや枚数によっては数十秒かかることがあります。しばらくお待ちください。"
                            :summary="summary"
                            :zip-url="response?.batch_download_url ?? null"
                            :count-label="`${files.length} / ${maxFiles}枚`"
                            @submit="submit({ level })"
                            @redo="resetResults"
                            @clear="clear"
                        >
                            <fieldset>
                                <legend
                                    class="text-sm font-medium text-ink-900"
                                >
                                    圧縮レベル
                                </legend>
                                <div class="mt-2 space-y-2">
                                    <label
                                        v-for="option in LEVELS"
                                        :key="option.value"
                                        class="flex cursor-pointer items-start gap-2 rounded-md border p-2 text-sm"
                                        :class="
                                            level === option.value
                                                ? 'border-gold-500 bg-gold-50'
                                                : 'border-ink-900/15'
                                        "
                                    >
                                        <input
                                            v-model="level"
                                            type="radio"
                                            name="level"
                                            :value="option.value"
                                            class="mt-1 accent-gold-500"
                                        />
                                        <span>
                                            <span
                                                class="font-medium text-ink-900"
                                                >{{ option.label }}</span
                                            >
                                            <span
                                                class="block text-xs text-ink-900/50"
                                                >{{ option.note }}</span
                                            >
                                        </span>
                                    </label>
                                </div>
                            </fieldset>
                        </BatchActionPanel>
                    </template>
                </ImageToolShell>
            </PageMain>
        </div>
    </PublicLayout>
</template>
