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

const mode = ref<'pixel' | 'percent'>('pixel');
const width = ref('');
const height = ref('');
const percent = ref('50');

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
    endpoint: '/image-resize',
    maxFiles: () => props.maxFiles,
    fallbackError:
        'リサイズ処理に失敗しました。時間をおいて再度お試しください。',
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

const canSubmit = computed(() => {
    if (files.value.length === 0) {
        return false;
    }

    if (mode.value === 'percent') {
        const value = Number(percent.value);

        return Number.isInteger(value) && value >= 1 && value <= 100;
    }

    return width.value !== '' || height.value !== '';
});

function submitFields(): Record<string, string> {
    if (mode.value === 'percent') {
        return { mode: 'percent', percent: percent.value };
    }

    return {
        mode: 'pixel',
        ...(width.value !== '' ? { width: width.value } : {}),
        ...(height.value !== '' ? { height: height.value } : {}),
    };
}

const summary = computed(() =>
    response.value ? summarize(response.value.results) : null,
);
</script>

<template>
    <Head title="画像リサイズ - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="画像リサイズ"
                :description="`JPEG・PNG・WebP・HEIC画像のサイズを変更します。縦横比は保ち、拡大はしません。1ファイル10MBまで、最大${maxFiles}枚まで一度に処理できます。`"
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
                            class="mb-4 text-sm text-red-600"
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
                            :can-submit="canSubmit"
                            submit-label="リサイズする"
                            processing-message="画像をリサイズしています。しばらくお待ちください。"
                            :summary="summary"
                            :zip-url="response?.batch_download_url ?? null"
                            :count-label="`${files.length} / ${maxFiles}枚`"
                            @submit="submit(submitFields())"
                            @redo="resetResults"
                            @clear="clear"
                        >
                            <fieldset>
                                <legend
                                    class="text-sm font-medium text-gray-900"
                                >
                                    サイズの指定
                                </legend>
                                <div class="mt-2 flex gap-4 text-sm">
                                    <label class="flex items-center gap-1">
                                        <input
                                            v-model="mode"
                                            type="radio"
                                            name="mode"
                                            value="pixel"
                                        />
                                        ピクセル
                                    </label>
                                    <label class="flex items-center gap-1">
                                        <input
                                            v-model="mode"
                                            type="radio"
                                            name="mode"
                                            value="percent"
                                        />
                                        パーセント
                                    </label>
                                </div>

                                <div
                                    v-if="mode === 'pixel'"
                                    class="mt-3 space-y-2"
                                >
                                    <label class="block text-sm">
                                        幅(px)
                                        <input
                                            v-model="width"
                                            type="number"
                                            name="width"
                                            min="1"
                                            max="8000"
                                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <label class="block text-sm">
                                        高さ(px)
                                        <input
                                            v-model="height"
                                            type="number"
                                            name="height"
                                            min="1"
                                            max="8000"
                                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <p class="text-xs text-gray-500">
                                        どちらか一方だけでも指定できます。縦横比は保たれます。拡大はできません（元より大きい値は、元のサイズに収めます）。
                                    </p>
                                </div>

                                <div v-else class="mt-3">
                                    <label class="block text-sm">
                                        縮小する割合(%)
                                        <input
                                            v-model="percent"
                                            type="number"
                                            name="percent"
                                            min="1"
                                            max="100"
                                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">
                                        1〜100。50 で縦横とも半分になります。
                                    </p>
                                </div>
                            </fieldset>
                        </BatchActionPanel>
                    </template>
                </ImageToolShell>
            </PageMain>
        </div>
    </PublicLayout>
</template>
