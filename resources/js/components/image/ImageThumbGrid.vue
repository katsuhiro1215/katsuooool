<script setup lang="ts">
import { formatBytes, isSuccess } from '@/lib/imageBatch';
import type { ThumbItem } from '@/lib/imageBatch';

defineProps<{ items: ThumbItem[]; canAdd: boolean; accept: string }>();

const emit = defineEmits<{ remove: [id: string]; add: [files: File[]] }>();

function extensionOf(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toUpperCase();
}

function onAdd(event: Event): void {
    const input = event.target as HTMLInputElement;
    emit('add', Array.from(input.files ?? []));
    input.value = '';
}
</script>

<template>
    <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
        <li
            v-for="item in items"
            :key="item.id"
            class="overflow-hidden rounded-lg border border-gray-200 bg-white"
        >
            <div class="relative aspect-square bg-gray-100">
                <img
                    v-if="item.previewUrl"
                    :src="item.previewUrl"
                    :alt="item.name"
                    class="h-full w-full object-cover"
                />
                <div
                    v-else
                    data-testid="thumb-fallback"
                    class="flex h-full w-full flex-col items-center justify-center p-2 text-center text-xs text-gray-500"
                >
                    <span class="text-base font-semibold">{{
                        extensionOf(item.name)
                    }}</span>
                    <span class="mt-1 break-all">{{ item.name }}</span>
                </div>

                <button
                    v-if="item.state === 'ready'"
                    type="button"
                    aria-label="削除"
                    class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-gray-900/70 text-sm text-white hover:bg-red-600"
                    @click="emit('remove', item.id)"
                >
                    ×
                </button>

                <div
                    v-if="item.state === 'processing'"
                    data-testid="thumb-processing"
                    class="absolute inset-0 flex items-center justify-center bg-white/70"
                >
                    <svg
                        class="h-6 w-6 animate-spin text-gray-600"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        />
                    </svg>
                </div>
            </div>

            <div class="space-y-1 p-2 text-xs">
                <p class="truncate text-gray-800" :title="item.name">
                    {{ item.name }}
                </p>

                <template
                    v-if="item.state === 'success' && isSuccess(item.result)"
                >
                    <p class="text-gray-600">
                        {{ formatBytes(item.result.input_bytes) }} →
                        {{ formatBytes(item.result.output_bytes) }}
                        <span
                            v-if="item.result.reduction_percent > 0"
                            class="font-medium text-green-700"
                            >({{ item.result.reduction_percent }}%削減)</span
                        >
                    </p>
                    <p
                        v-if="item.result.width && item.result.height"
                        class="text-gray-500"
                    >
                        {{ item.result.width }} × {{ item.result.height }}px
                    </p>
                    <p v-if="item.result.clamped" class="text-amber-700">
                        元のサイズに収めました（拡大はできません）
                    </p>
                    <p v-if="item.result.has_gps" class="text-amber-700">
                        位置情報(GPS)が残っています
                    </p>
                    <a
                        :href="item.result.download_url"
                        class="inline-block text-gray-900 underline hover:text-gray-600"
                        >ダウンロード</a
                    >
                </template>

                <p
                    v-else-if="
                        item.state === 'failed' &&
                        item.result &&
                        !isSuccess(item.result)
                    "
                    class="text-red-600"
                >
                    {{ item.result.reason }}
                </p>

                <p v-else class="text-gray-500">{{ formatBytes(item.size) }}</p>
            </div>
        </li>

        <li v-if="canAdd">
            <label
                class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white text-sm text-gray-500 transition hover:border-gray-400"
            >
                <span class="text-2xl">＋</span>
                <span>追加</span>
                <input
                    type="file"
                    class="hidden"
                    :accept="accept"
                    multiple
                    @change="onAdd"
                />
            </label>
        </li>
    </ul>
</template>
