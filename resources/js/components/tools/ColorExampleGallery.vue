<script setup lang="ts">
import axios from 'axios';
import { computed, ref } from 'vue';

interface ColorExampleData {
    id: number;
    title: string;
    colors: string[];
    angle: number | null;
    copy_count: number;
}

const props = defineProps<{
    examples: ColorExampleData[];
    type: 'palette' | 'gradient' | 'scheme';
}>();

const copiedId = ref<number | null>(null);

const routeNameByType: Record<typeof props.type, string> = {
    palette: 'color-palette.examples.copy',
    gradient: 'color-gradient.examples.copy',
    scheme: 'color-scheme.examples.copy',
};

function copyPayload(example: ColorExampleData): string {
    if (props.type === 'gradient') {
        return `background: linear-gradient(${example.angle}deg, ${example.colors[0]}, ${example.colors[1]});`;
    }

    return example.colors.join(', ');
}

const gradientPreview = computed(() => (example: ColorExampleData) =>
    `linear-gradient(${example.angle}deg, ${example.colors[0]}, ${example.colors[1]})`,
);

async function copy(example: ColorExampleData) {
    try {
        await navigator.clipboard.writeText(copyPayload(example));
        copiedId.value = example.id;
        setTimeout(() => {
            if (copiedId.value === example.id) {
                copiedId.value = null;
            }
        }, 1500);

        axios
            .post(route(routeNameByType[props.type], example.id))
            .catch(() => {
                // Analytics-only endpoint; ignore failures.
            });
    } catch {
        // Clipboard unavailable; the value is still visible as swatches.
    }
}
</script>

<template>
    <section v-if="examples.length > 0" class="mt-16">
        <h2 class="text-lg font-bold text-gray-900">人気の例</h2>
        <p class="mt-1 text-sm text-gray-600">
            気に入ったものをそのままコピーして使えます。
        </p>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div
                v-for="example in examples"
                :key="example.id"
                class="rounded-lg border border-gray-200 bg-white p-4"
            >
                <p class="mb-3 text-sm font-medium text-gray-900">
                    {{ example.title }}
                </p>

                <div
                    v-if="type === 'gradient'"
                    class="h-20 rounded-md border border-gray-200"
                    :style="{ backgroundImage: gradientPreview(example) }"
                />
                <div v-else class="flex gap-2">
                    <div
                        v-for="(hex, index) in example.colors"
                        :key="index"
                        class="h-12 w-12 rounded-md border border-gray-200"
                        :style="{ backgroundColor: hex }"
                    />
                </div>

                <button
                    type="button"
                    class="mt-3 rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:border-gray-400"
                    @click="copy(example)"
                >
                    {{ copiedId === example.id ? 'コピーしました' : 'コピー' }}
                </button>
            </div>
        </div>
    </section>
</template>
