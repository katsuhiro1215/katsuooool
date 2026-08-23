<script setup lang="ts">
import { computed } from 'vue';

interface DailyPoint {
    date: string;
    executions: number;
    errors: number;
}

const props = defineProps<{ series: DailyPoint[] }>();

const CHART_WIDTH = 720;
const CHART_HEIGHT = 160;
const SLOT_WIDTH = CHART_WIDTH / 30;
const BAR_WIDTH = 7;
const BAR_GAP = 2;

const maxValue = computed(() =>
    Math.max(
        1,
        ...props.series.flatMap((point) => [point.executions, point.errors]),
    ),
);

function barHeight(value: number) {
    return (value / maxValue.value) * (CHART_HEIGHT - 20);
}

const bars = computed(() =>
    props.series.map((point, index) => {
        const slotX = index * SLOT_WIDTH;
        const executionsHeight = barHeight(point.executions);
        const errorsHeight = barHeight(point.errors);

        return {
            date: point.date,
            executions: point.executions,
            errors: point.errors,
            executionsX: slotX,
            executionsY: CHART_HEIGHT - 20 - executionsHeight,
            executionsHeight,
            errorsX: slotX + BAR_WIDTH + BAR_GAP,
            errorsY: CHART_HEIGHT - 20 - errorsHeight,
            errorsHeight,
        };
    }),
);

const firstDate = computed(() => props.series[0]?.date ?? '');
const lastDate = computed(
    () => props.series[props.series.length - 1]?.date ?? '',
);
</script>

<template>
    <div>
        <div class="mb-2 flex items-center gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1">
                <span
                    class="inline-block h-2 w-2 rounded-sm bg-gray-700"
                ></span>
                実行数
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-2 w-2 rounded-sm bg-red-500"></span>
                エラー数
            </span>
        </div>

        <svg
            :viewBox="`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`"
            class="w-full"
            role="img"
            aria-label="直近30日間の実行数・エラー数の推移"
        >
            <line
                x1="0"
                :y1="CHART_HEIGHT - 20"
                :x2="CHART_WIDTH"
                :y2="CHART_HEIGHT - 20"
                stroke="#e5e5e5"
            />

            <g v-for="bar in bars" :key="bar.date">
                <rect
                    :x="bar.executionsX"
                    :y="bar.executionsY"
                    :width="BAR_WIDTH"
                    :height="bar.executionsHeight"
                    fill="#374151"
                >
                    <title>{{ bar.date }}: 実行 {{ bar.executions }}件</title>
                </rect>
                <rect
                    :x="bar.errorsX"
                    :y="bar.errorsY"
                    :width="BAR_WIDTH"
                    :height="bar.errorsHeight"
                    fill="#ef4444"
                >
                    <title>{{ bar.date }}: エラー {{ bar.errors }}件</title>
                </rect>
            </g>
        </svg>

        <div class="mt-1 flex justify-between text-xs text-gray-400">
            <span>{{ firstDate }}</span>
            <span>{{ lastDate }}</span>
        </div>
    </div>
</template>
