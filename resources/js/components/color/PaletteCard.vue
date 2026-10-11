<script setup lang="ts">
import type { Palette } from '@/lib/palette/types';

defineProps<{ palette: Palette; selected: boolean }>();
defineEmits<{ select: [] }>();
</script>

<template>
    <div class="w-40 shrink-0">
        <button
            type="button"
            :aria-pressed="selected"
            :aria-label="`${palette.label ?? 'パレット'} ${palette.colors.join(' ')}`"
            :class="[
                'block w-full overflow-hidden rounded-lg border-2 transition',
                selected
                    ? 'border-gray-900 shadow-md'
                    : 'border-transparent hover:border-gray-300',
            ]"
            @click="$emit('select')"
        >
            <span class="flex h-16">
                <span
                    v-for="(hex, index) in palette.colors"
                    :key="index"
                    data-swatch
                    class="flex-1"
                    :style="{ backgroundColor: hex }"
                />
            </span>
        </button>
        <p v-if="palette.label" class="mt-1 text-center text-xs text-gray-600">
            {{ palette.label }}
        </p>
    </div>
</template>
