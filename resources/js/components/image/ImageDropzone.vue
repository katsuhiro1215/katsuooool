<script setup lang="ts">
import { ref } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';

defineProps<{ accept: string; hint?: string }>();

const emit = defineEmits<{ files: [files: File[]] }>();

const isDragging = ref(false);

function onChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    emit('files', Array.from(input.files ?? []));
    input.value = '';
}

function onDrop(event: DragEvent): void {
    isDragging.value = false;
    emit('files', Array.from(event.dataTransfer?.files ?? []));
}
</script>

<template>
    <div
        class="flex min-h-[60vh] flex-col items-center justify-center rounded-lg border-2 border-dashed p-10 text-center transition"
        :class="
            isDragging
                ? 'border-gray-500 bg-gray-100'
                : 'border-gray-300 bg-white'
        "
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
    >
        <p class="text-base text-gray-600">
            ここに画像をドラッグ&ドロップ、または
        </p>
        <AppButton as="label" class="mt-4 inline-block">
            ファイルを選択
            <input
                type="file"
                class="hidden"
                :accept="accept"
                multiple
                @change="onChange"
            />
        </AppButton>
        <p v-if="hint" class="mt-4 text-xs text-gray-500">{{ hint }}</p>
    </div>
</template>
