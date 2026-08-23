<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface SuccessResult {
    original_name: string;
    status: 'success';
    input_bytes: number;
    output_bytes: number;
    reduction_percent: number;
    download_url: string;
}

interface RejectedOrFailedResult {
    original_name: string;
    status: 'rejected' | 'failed';
    reason: string;
}

type CompressResult = SuccessResult | RejectedOrFailedResult;

interface CompressResponse {
    results: CompressResult[];
    batch_download_url: string | null;
}

const props = defineProps<{ maxFiles: number }>();

const MAX_FILES = computed<number>(() => props.maxFiles);

const selectedFiles = ref<File[]>([]);
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const showPremiumUpsell = ref(false);
const response = ref<CompressResponse | null>(null);

const canCompress = computed(
    () => selectedFiles.value.length > 0 && !isProcessing.value,
);

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(1)} MB`;
}

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

async function compress() {
    if (!canCompress.value) {
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
        const { data } = await axios.post<CompressResponse>(
            '/image-compress',
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
                '圧縮処理に失敗しました。時間をおいて再度お試しください。';
        }
    } finally {
        isProcessing.value = false;
    }
}

function isSuccess(result: CompressResult): result is SuccessResult {
    return result.status === 'success';
}
</script>

<template>
    <Head title="画像圧縮 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">画像圧縮</h1>
                <p class="mt-2 text-sm text-gray-600">
                    JPEG・PNG・WebP画像を自動で最適化します。1ファイル10MBまで、最大{{
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
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        @change="onFileInputChange"
                    />
                </label>
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

            <button
                type="button"
                class="mt-6 w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                :disabled="!canCompress"
                @click="compress"
            >
                {{ isProcessing ? '圧縮しています...' : '圧縮する' }}
            </button>

            <div v-if="response" class="mt-8">
                <div v-if="response.batch_download_url" class="mb-4">
                    <a
                        :href="response.batch_download_url"
                        class="inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        まとめてダウンロード(ZIP)
                    </a>
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
                                {{ formatBytes(result.input_bytes) }} →
                                {{ formatBytes(result.output_bytes) }} ({{
                                    result.reduction_percent
                                }}% 削減)
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
        </main>
    </div>
</template>
