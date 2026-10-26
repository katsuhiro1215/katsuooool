<script setup lang="ts">
import { HEARING_STEPS } from '@/lib/hearing/questions';
import { isAnswered, stepFields  } from '@/lib/hearing/sheet';
import type {SheetState} from '@/lib/hearing/sheet';

defineProps<{ state: SheetState; current: number }>();
defineEmits<{ select: [index: number] }>();
</script>

<template>
    <nav aria-label="ヒアリングのステップ" class="overflow-x-auto">
        <ul class="flex min-w-max gap-1 border-b border-gray-200">
            <li v-for="(step, i) in HEARING_STEPS" :key="step.title">
                <button
                    type="button"
                    :data-step="i"
                    :aria-current="i === current ? 'step' : undefined"
                    :aria-label="`${i + 1} ${step.title} ${stepFields(i).filter((f) => isAnswered(state, f)).length}/${stepFields(i).length}項目回答済み`"
                    class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-3 text-base font-medium whitespace-nowrap transition"
                    :class="
                        i === current
                            ? 'border-gray-900 text-gray-900'
                            : 'border-transparent text-gray-500 hover:text-gray-800'
                    "
                    @click="$emit('select', i)"
                >
                    <span class="text-sm text-gray-400">0{{ i + 1 }}</span>
                    {{ step.title }}
                    <span
                        class="rounded-full px-2 py-0.5 text-sm"
                        :class="
                            stepFields(i).every((f) => isAnswered(state, f))
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-gray-100 text-gray-600'
                        "
                    >
                        {{
                            stepFields(i).filter((f) => isAnswered(state, f))
                                .length
                        }}/{{ stepFields(i).length }}
                    </span>
                </button>
            </li>
        </ul>
    </nav>
</template>
