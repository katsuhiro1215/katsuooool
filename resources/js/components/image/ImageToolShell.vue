<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

import ImageDropzone from '@/components/image/ImageDropzone.vue';

defineProps<{
    phase: 'empty' | 'ready' | 'processing' | 'done';
    accept: string;
    hint?: string;
}>();

const emit = defineEmits<{ files: [files: File[]] }>();

// ページのどこにドロップしても受け付ける（ブラウザが画像を開いてしまうのも防ぐ）
function hasFiles(event: DragEvent): boolean {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

function onWindowDragOver(event: DragEvent): void {
    if (hasFiles(event)) {
        event.preventDefault();
    }
}

function onWindowDrop(event: DragEvent): void {
    if (!hasFiles(event) || event.defaultPrevented) {
        return;
    }

    event.preventDefault();
    emit('files', Array.from(event.dataTransfer?.files ?? []));
}

onMounted(() => {
    window.addEventListener('dragover', onWindowDragOver);
    window.addEventListener('drop', onWindowDrop);
});

onBeforeUnmount(() => {
    window.removeEventListener('dragover', onWindowDragOver);
    window.removeEventListener('drop', onWindowDrop);
});
</script>

<template>
    <ImageDropzone
        v-if="phase === 'empty'"
        :accept="accept"
        :hint="hint"
        @files="emit('files', $event)"
    />

    <div v-else class="lg:flex lg:items-start lg:gap-6">
        <div class="min-w-0 flex-1">
            <slot name="grid" />
        </div>
        <aside class="mt-6 w-full shrink-0 lg:mt-0 lg:w-80">
            <slot name="options" />
        </aside>
    </div>
</template>
