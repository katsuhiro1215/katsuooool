<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { resolveErrorMessage } from '@/lib/toolErrorMessage';

interface SuccessResult {
    original_name: string;
    status: 'success';
    width: number;
    height: number;
    download_url: string;
}

interface RejectedOrFailedResult {
    original_name: string;
    status: 'rejected' | 'failed';
    reason: string;
}

type RotateResult = SuccessResult | RejectedOrFailedResult;

interface RotateResponse {
    results: RotateResult[];
    batch_download_url: string | null;
}

type Direction = 'left' | '180' | 'right';

const DIRECTION_OPTIONS: { value: Direction; label: string }[] = [
    { value: 'left', label: '左に90度' },
    { value: '180', label: '180度' },
    { value: 'right', label: '右に90度' },
];

// Matches ImageRotateService's server-side rotation: Intervention Image
// treats a positive angle as counter-clockwise, the opposite of CSS's
// rotate(), so "left" (counter-clockwise) and "right" (clockwise) are
// negated relative to their Intervention angle to look the same on screen.
const PREVIEW_ROTATION_DEGREES: Record<Direction, number> = {
    left: -90,
    '180': 180,
    right: 90,
};

const props = defineProps<{ maxFiles: number }>();

const MAX_FILES = computed<number>(() => props.maxFiles);

const selectedFiles = ref<File[]>([]);
const direction = ref<Direction>('left');
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const showPremiumUpsell = ref(false);
const response = ref<RotateResponse | null>(null);

const canRotate = computed(
    () => selectedFiles.value.length > 0 && !isProcessing.value,
);

const previewRotationDegrees = computed(
    () => PREVIEW_ROTATION_DEGREES[direction.value],
);

const previewUrls = ref<string[]>([]);

watch(selectedFiles, (files) => {
    previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
    previewUrls.value = files.map((file) => URL.createObjectURL(file));
});

onBeforeUnmount(() => {
    previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
});

function addFiles(files: FileList | null) {
    if (!files) {
        return;
    }

    errorMessage.value = '';
    showPremiumUpsell.value = false;
    response.value = null;

    const incoming = Array.from(files);
    const combined = [...selectedFiles.value, ...incoming].slice(
        0,
        MAX_FILES.value,
    );

    if (selectedFiles.value.length + incoming.length > MAX_FILES.value) {
        errorMessage.value = `一度にアップロードできるのは最大${MAX_FILES.value}枚までです。`;
    }

    selectedFiles.value = combined;
}

function onFileInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    addFiles(target.files);
    target.value = '';
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function removeFile(index: number) {
    selectedFiles.value = selectedFiles.value.filter((_, i) => i !== index);
}

async function rotate() {
    if (!canRotate.value) {
        return;
    }

    isProcessing.value = true;
    errorMessage.value = '';
    showPremiumUpsell.value = false;
    response.value = null;

    const formData = new FormData();
    selectedFiles.value.forEach((file) => {
        formData.append('files[]', file);
    });
    formData.append('direction', direction.value);

    try {
        const { data } = await axios.post<RotateResponse>(
            '/image-rotate',
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );

        response.value = data;
    } catch (error) {
        const result = resolveErrorMessage(
            error,
            MAX_FILES.value,
            '回転処理に失敗しました。時間をおいて再度お試しください。',
        );
        errorMessage.value = result.message;
        showPremiumUpsell.value = result.showUpsell;
    } finally {
        isProcessing.value = false;
    }
}

function isSuccess(result: RotateResult): result is SuccessResult {
    return result.status === 'success';
}
</script>

<template>
    <Head title="画像の回転 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="画像の回転"
                :description="`JPEG・PNG・WebP画像を左90度・180度・右90度のいずれかに一括回転します。1ファイル10MBまで、最大${MAX_FILES}枚まで一度に処理できます。`"
            />

            <PageMain>
                <fieldset class="mb-6">
                    <legend class="text-sm font-medium text-gray-700">
                        回転方向
                    </legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="option in DIRECTION_OPTIONS"
                            :key="option.value"
                            class="cursor-pointer rounded-md border px-4 py-2 text-sm transition"
                            :class="
                                direction === option.value
                                    ? 'border-gray-900 bg-gray-900 text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'
                            "
                        >
                            <input
                                v-model="direction"
                                type="radio"
                                :value="option.value"
                                class="sr-only"
                            />
                            {{ option.label }}
                        </label>
                    </div>
                </fieldset>

                <div
                    class="rounded-lg border-2 border-dashed p-10 text-center transition"
                    :class="
                        isDragging
                            ? 'border-gray-400 bg-gray-100'
                            : 'border-gray-300 bg-white'
                    "
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <p class="text-sm text-gray-600">
                        ここに画像をドラッグ&ドロップ、または
                    </p>
                    <AppButton as="label" class="mt-3 inline-block">
                        ファイルを選択
                        <input
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            @change="onFileInputChange"
                        />
                    </AppButton>
                </div>

                <div v-if="errorMessage" class="mt-4 text-sm text-red-600">
                    <p>{{ errorMessage }}</p>
                    <Link
                        v-if="showPremiumUpsell"
                        href="/premium"
                        class="mt-1 inline-block underline"
                    >
                        Premiumについて詳しく見る
                    </Link>
                </div>

                <ul
                    v-if="selectedFiles.length > 0"
                    class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
                >
                    <li
                        v-for="(file, index) in selectedFiles"
                        :key="`${file.name}-${index}`"
                        class="rounded-md border border-gray-200 bg-white p-3 text-sm"
                    >
                        <div
                            class="flex aspect-square items-center justify-center overflow-hidden rounded bg-gray-100"
                        >
                            <img
                                :src="previewUrls[index]"
                                :alt="file.name"
                                class="rotate-preview h-full w-full object-contain transition-transform duration-200"
                                :style="{
                                    transform: `rotate(${previewRotationDegrees}deg)`,
                                }"
                            />
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="truncate text-gray-800">{{
                                file.name
                            }}</span>
                            <button
                                type="button"
                                class="ml-2 shrink-0 text-gray-400 hover:text-red-600"
                                @click="removeFile(index)"
                            >
                                削除
                            </button>
                        </div>
                    </li>
                </ul>

                <AppButton
                    type="button"
                    class="mt-6 w-full"
                    :disabled="!canRotate"
                    @click="rotate"
                >
                    {{ isProcessing ? '回転しています...' : '回転する' }}
                </AppButton>

                <div v-if="response" class="mt-8">
                    <div v-if="response.batch_download_url" class="mb-4">
                        <AppButton
                            as="a"
                            :href="response.batch_download_url"
                            class="inline-block"
                        >
                            まとめてダウンロード(ZIP)
                        </AppButton>
                    </div>

                    <ul class="space-y-2">
                        <li
                            v-for="(result, index) in response.results"
                            :key="`${result.original_name}-${index}`"
                            class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm"
                        >
                            <template v-if="isSuccess(result)">
                                <div class="flex items-center justify-between">
                                    <span class="truncate text-gray-800">{{
                                        result.original_name
                                    }}</span>
                                    <a
                                        :href="result.download_url"
                                        class="text-gray-900 underline hover:text-gray-600"
                                        >ダウンロード</a
                                    >
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ result.width }} × {{ result.height }} px
                                </p>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-between">
                                    <span class="truncate text-gray-500">{{
                                        result.original_name
                                    }}</span>
                                    <span class="text-xs text-red-600">{{
                                        result.reason
                                    }}</span>
                                </div>
                            </template>
                        </li>
                    </ul>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
