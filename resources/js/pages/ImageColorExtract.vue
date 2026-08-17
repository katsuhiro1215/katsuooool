<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

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

const MAX_FILES = 10;

const selectedFiles = ref<File[]>([]);
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const response = ref<ColorExtractResponse | null>(null);
const copiedHex = ref('');

const canExtract = computed(
    () => selectedFiles.value.length > 0 && !isProcessing.value,
);

function addFiles(files: FileList | null) {
    if (!files) {
        return;
    }

    errorMessage.value = '';
    response.value = null;

    const incoming = Array.from(files);
    const combined = [...selectedFiles.value, ...incoming].slice(0, MAX_FILES);

    if (selectedFiles.value.length + incoming.length > MAX_FILES) {
        errorMessage.value = `一度にアップロードできるのは最大${MAX_FILES}枚までです。`;
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
    } catch {
        errorMessage.value =
            '色の抽出に失敗しました。時間をおいて再度お試しください。';
    } finally {
        isProcessing.value = false;
    }
}

async function copyHex(hex: string) {
    try {
        await navigator.clipboard.writeText(hex);
        copiedHex.value = hex;
        setTimeout(() => {
            if (copiedHex.value === hex) {
                copiedHex.value = '';
            }
        }, 1500);
    } catch {
        // Clipboard API unavailable (e.g. insecure context); ignore silently.
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
            <div class="mx-auto max-w-3xl px-6 py-10">
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

        <main class="mx-auto max-w-3xl px-6 py-10">
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
                <label
                    class="mt-3 inline-block cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                >
                    ファイルを選択
                    <input
                        type="file"
                        class="hidden"
                        accept="image/jpeg,image/png,image/webp,image/avif"
                        multiple
                        @change="onFileInputChange"
                    />
                </label>
            </div>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                {{ errorMessage }}
            </p>

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

            <button
                type="button"
                class="mt-6 w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                :disabled="!canExtract"
                @click="extract"
            >
                {{ isProcessing ? '抽出しています...' : '抽出する' }}
            </button>

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
