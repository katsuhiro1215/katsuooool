<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';

interface SuccessResponse {
    status: 'success';
    download_url: string;
}

interface FailedResponse {
    status: 'failed';
    reason: string;
}

type GenerateResponse = SuccessResponse | FailedResponse;

const selectedFile = ref<File | null>(null);
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const response = ref<GenerateResponse | null>(null);

const canGenerate = computed(
    () => selectedFile.value !== null && !isProcessing.value,
);

function setFile(files: FileList | null) {
    if (!files || files.length === 0) {
        return;
    }

    errorMessage.value = '';
    response.value = null;
    selectedFile.value = files[0];
}

function onFileInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    setFile(target.files);
    target.value = '';
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    setFile(event.dataTransfer?.files ?? null);
}

function removeFile() {
    selectedFile.value = null;
    response.value = null;
}

async function generate() {
    if (!selectedFile.value || !canGenerate.value) {
        return;
    }

    isProcessing.value = true;
    errorMessage.value = '';
    response.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    try {
        const { data } = await axios.post<GenerateResponse>(
            '/favicon-generate',
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );

        response.value = data;
    } catch (error) {
        errorMessage.value =
            axios.isAxiosError(error) && error.response?.status === 422
                ? '対応していないファイル形式か、ファイルサイズが上限(10MB)を超えています。'
                : 'faviconの生成に失敗しました。時間をおいて再度お試しください。';
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Head title="favicon生成 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    favicon生成
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    1枚の画像から6サイズ(16×16 / 32×32 / 48×48 / 180×180 /
                    192×192 /
                    512×512)のfavicon用PNGをまとめて生成します。1ファイル10MBまでです。
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
                <AppButton as="label" class="mt-3 inline-block">
                    ファイルを選択
                    <input
                        type="file"
                        class="hidden"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onFileInputChange"
                    />
                </AppButton>
            </div>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                {{ errorMessage }}
            </p>

            <ul v-if="selectedFile" class="mt-6 space-y-2">
                <li
                    class="flex items-center justify-between rounded-md border border-gray-200 bg-white px-4 py-2 text-sm"
                >
                    <span class="truncate text-gray-800">{{
                        selectedFile.name
                    }}</span>
                    <button
                        type="button"
                        class="ml-4 text-gray-400 hover:text-red-600"
                        @click="removeFile"
                    >
                        削除
                    </button>
                </li>
            </ul>

            <AppButton
                type="button"
                class="mt-6 w-full"
                :disabled="!canGenerate"
                @click="generate"
            >
                {{ isProcessing ? '生成しています...' : '生成する' }}
            </AppButton>

            <div v-if="response" class="mt-8">
                <div
                    v-if="response.status === 'success'"
                    class="rounded-md border border-gray-200 bg-white p-4"
                >
                    <AppButton
                        as="a"
                        :href="response.download_url"
                        class="inline-block"
                    >
                        まとめてダウンロード(ZIP)
                    </AppButton>
                </div>
                <p v-else class="text-sm text-red-600">
                    {{ response.reason }}
                </p>
            </div>
        </main>
    </div>
</template>
