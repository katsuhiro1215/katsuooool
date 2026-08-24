<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, ref } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';

interface CropSuccessResponse {
    status: 'success';
    width: number;
    height: number;
    download_url: string;
}

interface CropFailureResponse {
    status: 'rejected' | 'failed';
    reason: string;
}

type CropResponse = CropSuccessResponse | CropFailureResponse;

type AspectRatio = 'free' | '1:1' | '4:3' | '16:9' | '3:2';

const ASPECT_RATIO_OPTIONS: {
    value: AspectRatio;
    label: string;
    ratio: number | null;
}[] = [
    { value: 'free', label: 'フリー', ratio: null },
    { value: '1:1', label: '1:1', ratio: 1 },
    { value: '4:3', label: '4:3', ratio: 4 / 3 },
    { value: '16:9', label: '16:9', ratio: 16 / 9 },
    { value: '3:2', label: '3:2', ratio: 3 / 2 },
];

const PREVIEW_MAX_WIDTH = 600;
const PREVIEW_MAX_HEIGHT = 450;
const MIN_BOX_SIZE = 20;

const selectedFile = ref<File | null>(null);
const imageUrl = ref('');
const naturalWidth = ref(0);
const naturalHeight = ref(0);
const previewWidth = ref(0);
const previewHeight = ref(0);
const aspectRatio = ref<AspectRatio>('free');
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const response = ref<CropResponse | null>(null);

const box = ref({ x: 0, y: 0, width: 0, height: 0 });

const activeRatio = computed(
    () =>
        ASPECT_RATIO_OPTIONS.find(
            (option) => option.value === aspectRatio.value,
        )?.ratio ?? null,
);

const canCrop = computed(
    () => selectedFile.value !== null && !isProcessing.value,
);

function resetBox() {
    const ratio = activeRatio.value;
    const maxW = previewWidth.value * 0.8;
    const maxH = previewHeight.value * 0.8;

    let w = maxW;
    let h = ratio ? w / ratio : maxH;

    if (h > maxH) {
        h = maxH;
        w = ratio ? h * ratio : w;
    }

    box.value = {
        x: (previewWidth.value - w) / 2,
        y: (previewHeight.value - h) / 2,
        width: w,
        height: h,
    };
}

function loadFile(file: File) {
    errorMessage.value = '';
    response.value = null;

    if (imageUrl.value) {
        URL.revokeObjectURL(imageUrl.value);
    }

    selectedFile.value = file;
    imageUrl.value = URL.createObjectURL(file);

    const img = new Image();
    img.onload = () => {
        naturalWidth.value = img.naturalWidth;
        naturalHeight.value = img.naturalHeight;

        const scale = Math.min(
            PREVIEW_MAX_WIDTH / img.naturalWidth,
            PREVIEW_MAX_HEIGHT / img.naturalHeight,
            1,
        );

        previewWidth.value = Math.round(img.naturalWidth * scale);
        previewHeight.value = Math.round(img.naturalHeight * scale);

        resetBox();
    };
    img.src = imageUrl.value;
}

function onFileInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        loadFile(file);
    }

    target.value = '';
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0];

    if (file) {
        loadFile(file);
    }
}

function setAspectRatio(value: AspectRatio) {
    aspectRatio.value = value;
    resetBox();
}

type DragMode = 'move' | 'resize-nw' | 'resize-ne' | 'resize-sw' | 'resize-se';

let dragMode: DragMode | null = null;
let dragStartX = 0;
let dragStartY = 0;
let dragStartBox = { x: 0, y: 0, width: 0, height: 0 };

function startDrag(mode: DragMode, event: PointerEvent) {
    dragMode = mode;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    dragStartBox = { ...box.value };

    window.addEventListener('pointermove', onDragMove);
    window.addEventListener('pointerup', stopDrag);
}

function clamp(value: number, min: number, max: number) {
    return Math.min(Math.max(value, min), max);
}

function onDragMove(event: PointerEvent) {
    if (!dragMode) {
        return;
    }

    const dx = event.clientX - dragStartX;
    const dy = event.clientY - dragStartY;
    const ratio = activeRatio.value;

    if (dragMode === 'move') {
        box.value = {
            ...dragStartBox,
            x: clamp(
                dragStartBox.x + dx,
                0,
                previewWidth.value - dragStartBox.width,
            ),
            y: clamp(
                dragStartBox.y + dy,
                0,
                previewHeight.value - dragStartBox.height,
            ),
        };

        return;
    }

    let { x, y, width, height } = dragStartBox;

    if (dragMode === 'resize-se') {
        width = clamp(
            dragStartBox.width + dx,
            MIN_BOX_SIZE,
            previewWidth.value - x,
        );
        height = ratio
            ? width / ratio
            : clamp(
                  dragStartBox.height + dy,
                  MIN_BOX_SIZE,
                  previewHeight.value - y,
              );
    } else if (dragMode === 'resize-nw') {
        width = clamp(
            dragStartBox.width - dx,
            MIN_BOX_SIZE,
            dragStartBox.x + dragStartBox.width,
        );
        height = ratio
            ? width / ratio
            : clamp(
                  dragStartBox.height - dy,
                  MIN_BOX_SIZE,
                  dragStartBox.y + dragStartBox.height,
              );
        x = dragStartBox.x + dragStartBox.width - width;
        y = dragStartBox.y + dragStartBox.height - height;
    } else if (dragMode === 'resize-ne') {
        width = clamp(
            dragStartBox.width + dx,
            MIN_BOX_SIZE,
            previewWidth.value - x,
        );
        height = ratio
            ? width / ratio
            : clamp(
                  dragStartBox.height - dy,
                  MIN_BOX_SIZE,
                  dragStartBox.y + dragStartBox.height,
              );
        y = dragStartBox.y + dragStartBox.height - height;
    } else if (dragMode === 'resize-sw') {
        width = clamp(
            dragStartBox.width - dx,
            MIN_BOX_SIZE,
            dragStartBox.x + dragStartBox.width,
        );
        height = ratio
            ? width / ratio
            : clamp(
                  dragStartBox.height + dy,
                  MIN_BOX_SIZE,
                  previewHeight.value - y,
              );
        x = dragStartBox.x + dragStartBox.width - width;
    }

    if (y + height > previewHeight.value) {
        height = previewHeight.value - y;
        width = ratio ? height * ratio : width;
    }

    if (x + width > previewWidth.value) {
        width = previewWidth.value - x;
        height = ratio ? width / ratio : height;
    }

    box.value = { x, y, width, height };
}

function stopDrag() {
    dragMode = null;
    window.removeEventListener('pointermove', onDragMove);
    window.removeEventListener('pointerup', stopDrag);
}

onBeforeUnmount(() => {
    stopDrag();

    if (imageUrl.value) {
        URL.revokeObjectURL(imageUrl.value);
    }
});

const boxStyle = computed(() => ({
    left: `${box.value.x}px`,
    top: `${box.value.y}px`,
    width: `${box.value.width}px`,
    height: `${box.value.height}px`,
}));

async function crop() {
    if (!canCrop.value || !selectedFile.value) {
        return;
    }

    isProcessing.value = true;
    errorMessage.value = '';
    response.value = null;

    const scale = naturalWidth.value / previewWidth.value;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('x', String(Math.round(box.value.x * scale)));
    formData.append('y', String(Math.round(box.value.y * scale)));
    formData.append('width', String(Math.round(box.value.width * scale)));
    formData.append('height', String(Math.round(box.value.height * scale)));

    try {
        const { data } = await axios.post<CropResponse>(
            '/image-crop',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        response.value = data;
    } catch {
        errorMessage.value =
            '切り抜き処理に失敗しました。時間をおいて再度お試しください。';
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Head title="画像の切り抜き - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    画像の切り抜き
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    画像をアップロードし、ドラッグで範囲を選択して切り抜きます。JPEG・PNG・WebP、10MBまでの1ファイルに対応しています。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                v-if="!selectedFile"
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

            <template v-else>
                <fieldset class="mb-4">
                    <legend class="text-sm font-medium text-gray-700">
                        アスペクト比
                    </legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="option in ASPECT_RATIO_OPTIONS"
                            :key="option.value"
                            type="button"
                            class="rounded-md border px-4 py-2 text-sm transition"
                            :class="
                                aspectRatio === option.value
                                    ? 'border-gray-900 bg-gray-900 text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'
                            "
                            @click="setAspectRatio(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </fieldset>

                <div
                    class="relative mx-auto touch-none select-none overflow-hidden rounded-md border border-gray-200 bg-gray-900"
                    :style="{
                        width: `${previewWidth}px`,
                        height: `${previewHeight}px`,
                    }"
                >
                    <img
                        :src="imageUrl"
                        alt="切り抜き対象の画像"
                        class="pointer-events-none absolute inset-0 h-full w-full object-contain"
                    />
                    <div
                        class="absolute cursor-move border-2 border-white shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]"
                        :style="boxStyle"
                        @pointerdown.prevent="(e) => startDrag('move', e)"
                    >
                        <div
                            class="absolute -left-1.5 -top-1.5 h-3 w-3 cursor-nwse-resize rounded-full border border-gray-900 bg-white"
                            @pointerdown.stop.prevent="
                                (e) => startDrag('resize-nw', e)
                            "
                        ></div>
                        <div
                            class="absolute -right-1.5 -top-1.5 h-3 w-3 cursor-nesw-resize rounded-full border border-gray-900 bg-white"
                            @pointerdown.stop.prevent="
                                (e) => startDrag('resize-ne', e)
                            "
                        ></div>
                        <div
                            class="absolute -bottom-1.5 -left-1.5 h-3 w-3 cursor-nesw-resize rounded-full border border-gray-900 bg-white"
                            @pointerdown.stop.prevent="
                                (e) => startDrag('resize-sw', e)
                            "
                        ></div>
                        <div
                            class="absolute -bottom-1.5 -right-1.5 h-3 w-3 cursor-nwse-resize rounded-full border border-gray-900 bg-white"
                            @pointerdown.stop.prevent="
                                (e) => startDrag('resize-se', e)
                            "
                        ></div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <span class="truncate text-sm text-gray-600">{{
                        selectedFile.name
                    }}</span>
                    <button
                        type="button"
                        class="text-sm text-gray-400 hover:text-red-600"
                        @click="selectedFile = null"
                    >
                        画像を変更
                    </button>
                </div>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                    {{ errorMessage }}
                </p>

                <AppButton
                    type="button"
                    class="mt-6 w-full"
                    :disabled="!canCrop"
                    @click="crop"
                >
                    {{ isProcessing ? '切り抜いています...' : '切り抜く' }}
                </AppButton>

                <div v-if="response" class="mt-8">
                    <div
                        v-if="response.status === 'success'"
                        class="flex items-center justify-between rounded-md border border-gray-200 bg-white px-4 py-3 text-sm"
                    >
                        <span class="text-gray-800">
                            {{ response.width }} × {{ response.height }} px
                        </span>
                        <a
                            :href="response.download_url"
                            class="text-gray-900 underline hover:text-gray-600"
                            >ダウンロード</a
                        >
                    </div>
                    <p v-else class="text-sm text-red-600">
                        {{ response.reason }}
                    </p>
                </div>
            </template>
        </main>
    </div>
</template>
