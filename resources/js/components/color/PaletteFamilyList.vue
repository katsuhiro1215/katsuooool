<script setup lang="ts">
import type { Palette } from '@/lib/palette/types';

import PaletteCard from './PaletteCard.vue';

defineProps<{
    groups: { key: string; label: string; palettes: Palette[] }[];
    selectedId: string | null;
}>();
defineEmits<{ select: [id: string] }>();
</script>

<template>
    <div class="space-y-6">
        <section v-for="group in groups" :key="group.key">
            <h2 class="text-sm font-medium text-gray-700">{{ group.label }}</h2>
            <div class="mt-2 flex flex-wrap gap-4">
                <PaletteCard
                    v-for="palette in group.palettes"
                    :key="palette.id"
                    :palette="palette"
                    :selected="palette.id === selectedId"
                    @select="$emit('select', palette.id)"
                />
            </div>
        </section>
    </div>
</template>
