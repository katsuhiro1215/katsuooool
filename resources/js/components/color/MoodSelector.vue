<script setup lang="ts">
import { MOODS } from '@/lib/palette/moods';
import type { MoodId } from '@/lib/palette/types';

const props = defineProps<{
    mode: 'mood' | 'base';
    mood: MoodId;
    base: string;
}>();

const emit = defineEmits<{
    'select-mood': [id: MoodId];
    'select-base': [hex: string];
}>();

function onBaseInput(event: Event) {
    emit('select-base', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <div>
        <p class="text-sm font-medium text-gray-700">
            どんな雰囲気にしますか？
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="item in MOODS"
                :key="item.id"
                type="button"
                :data-mood="item.id"
                :aria-pressed="props.mode === 'mood' && props.mood === item.id"
                :title="item.hint"
                :class="[
                    'rounded-full border px-4 py-1.5 text-sm transition',
                    props.mode === 'mood' && props.mood === item.id
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-500',
                ]"
                @click="emit('select-mood', item.id)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button
                type="button"
                data-base-toggle
                :aria-pressed="props.mode === 'base'"
                :class="[
                    'rounded-full border px-4 py-1.5 text-sm transition',
                    props.mode === 'base'
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-500',
                ]"
                @click="emit('select-base', props.base)"
            >
                基準色から作る
            </button>
            <input
                v-if="props.mode === 'base'"
                type="color"
                :value="props.base"
                aria-label="基準色"
                class="h-9 w-16 cursor-pointer rounded-md border border-gray-200"
                @input="onBaseInput"
            />
            <span
                v-if="props.mode === 'base'"
                class="font-mono text-sm text-gray-600"
                >{{ props.base }}</span
            >
        </div>
    </div>
</template>
