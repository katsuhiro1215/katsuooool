<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';

interface ExtractedColor {
    hex: string;
    rgb: [number, number, number];
}

interface SuccessResult {
    original_name: string;
    status: 'success';
    colors: ExtractedColor[];
}

interface RejectedOrFailedResult {
    original_name: string;
    status: 'rejected' | 'failed';
    reason: string;
}

type ColorExtractResult = SuccessResult | RejectedOrFailedResult;

interface ColorExtractResponse {
    results: ColorExtractResult[];
}

const props = defineProps<{ maxFiles: number }>();

const MAX_FILES = computed<number>(() => props.maxFiles);

const selectedFiles = ref<File[]>([]);
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const showPremiumUpsell = ref(false);
const response = ref<ColorExtractResponse | null>(null);
const copiedHex = ref('');
const copyFailed = ref(false);

const canExtract = computed(
    () => selectedFiles.value.length > 0 && !isProcessing.value,
);

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

async function extract() {
    if (!canExtract.value) {
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

    try {
        const { data } = await axios.post<ColorExtractResponse>(
            '/image-color-extract',
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );

        response.value = data;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            if (MAX_FILES.value < 10) {
                errorMessage.value = `無料プランでは一度に${MAX_FILES.value}枚までです。Premium(¥600/月)なら10枚まで一括処理できます。`;
                showPremiumUpsell.value = true;
            } else {
                errorMessage.value = 'ファイル数が多すぎます。';
            }
        } else if (
            axios.isAxiosError(error) &&
            error.response?.status === 429
        ) {
            if (MAX_FILES.value < 10) {
                errorMessage.value =
                    'リクエストが多すぎます。しばらく時間をおいてから再度お試しください。Premiumならレート制限が緩和されます。';
                showPremiumUpsell.value = true;
            } else {
                errorMessage.value =
                    'リクエストが多すぎます。しばらく時間をおいてから再度お試しください。';
            }
        } else {
            errorMessage.value =
                '色の抽出に失敗しました。時間をおいて再度お試しください。';
        }
    } finally {
        isProcessing.value = false;
    }
}

async function copyHex(hex: string) {
    copyFailed.value = false;

    try {
        await navigator.clipboard.writeText(hex);
        copiedHex.value = hex;
        setTimeout(() => {
            if (copiedHex.value === hex) {
                copiedHex.value = '';
            }
        }, 1500);
    } catch {
        // Clipboard API unavailable (e.g. insecure context) or permission
        // denied; surface it instead of leaving the click looking inert.
        copyFailed.value = true;
        setTimeout(() => {
            copyFailed.value = false;
        }, 1500);
    }
}

function isSuccess(result: ColorExtractResult): result is SuccessResult {
    return result.status === 'success';
}
</script>

<template>
    <Head title="画像から色を抽出 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    画像から色を抽出
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    JPEG・PNG・WebP・AVIF画像から主要な色を最大6色抽出し、HEXコードで表示します。1ファイル10MBまで、最大{{
                        MAX_FILES
                    }}枚まで一度に処理できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
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
                        accept="image/jpeg,image/png,image/webp,image/avif"
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

            <ul v-if="selectedFiles.length > 0" class="mt-6 space-y-2">
                <li
                    v-for="(file, index) in selectedFiles"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between rounded-md border border-gray-200 bg-white px-4 py-2 text-sm"
                >
                    <span class="truncate text-gray-800">{{ file.name }}</span>
                    <button
                        type="button"
                        class="ml-4 text-gray-400 hover:text-red-600"
                        @click="removeFile(index)"
                    >
                        削除
                    </button>
                </li>
            </ul>

            <AppButton
                type="button"
                class="mt-6 w-full"
                :disabled="!canExtract"
                @click="extract"
            >
                {{ isProcessing ? '抽出しています...' : '抽出する' }}
            </AppButton>

            <p v-if="copyFailed" class="mt-4 text-sm text-red-600">
                コピーできませんでした。HEXコードを手動で選択してください。
            </p>

            <div v-if="response" class="mt-8 space-y-4">
                <div
                    v-for="(result, index) in response.results"
                    :key="`${result.original_name}-${index}`"
                    class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm"
                >
                    <template v-if="isSuccess(result)">
                        <p class="truncate text-gray-800">
                            {{ result.original_name }}
                        </p>
                        <ul class="mt-3 flex flex-wrap gap-3">
                            <li
                                v-for="color in result.colors"
                                :key="color.hex"
                                class="flex flex-col items-center gap-1"
                            >
                                <button
                                    type="button"
                                    class="h-12 w-12 rounded-md border border-gray-200"
                                    :style="{ backgroundColor: color.hex }"
                                    :title="`${color.hex} をコピー`"
                                    @click="copyHex(color.hex)"
                                />
                                <span class="font-mono text-xs text-gray-600">
                                    {{
                                        copiedHex === color.hex
                                            ? 'コピーしました'
                                            : color.hex
                                    }}
                                </span>
                            </li>
                        </ul>
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
                </div>
            </div>
        </main>
    </div>
</template>
