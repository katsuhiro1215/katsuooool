<script setup lang="ts">
import { computed, ref } from 'vue';

import { toCssVariables, toHexList, toTailwind } from '@/lib/palette/export';
import { ROLE_INFO, contrastChecks } from '@/lib/palette/roles';
import { ROLE_KEYS } from '@/lib/palette/types';
import type { Palette } from '@/lib/palette/types';

const props = defineProps<{ palette: Palette }>();

const copied = ref('');
let resetTimer: ReturnType<typeof setTimeout> | null = null;

async function copy(key: string, text: string) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;

        if (resetTimer) {
            clearTimeout(resetTimer);
        }

        resetTimer = setTimeout(() => {
            copied.value = '';
        }, 1500);
    } catch {
        // クリップボードが使えなくても、値は画面に見えている。
    }
}

const roles = computed(() =>
    ROLE_KEYS.map((key, index) => ({
        key,
        hex: props.palette.colors[index],
        ...ROLE_INFO[key],
    })),
);

const checks = computed(() => contrastChecks(props.palette.colors));

const exports = computed(() => [
    {
        id: 'css',
        label: 'CSS変数をコピー',
        text: toCssVariables(props.palette.colors),
    },
    {
        id: 'tailwind',
        label: 'Tailwindをコピー',
        text: toTailwind(props.palette.colors),
    },
    {
        id: 'hex',
        label: 'HEX一覧をコピー',
        text: toHexList(props.palette.colors),
    },
]);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-sm font-medium text-gray-700">役割と割合の目安</h2>
            <ul
                class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white"
            >
                <li
                    v-for="role in roles"
                    :key="role.key"
                    class="flex items-center gap-3 px-3 py-2"
                >
                    <span
                        class="h-8 w-8 shrink-0 rounded-md border border-gray-200"
                        :style="{ backgroundColor: role.hex }"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900">
                            {{ role.label }}
                            <span
                                v-if="role.share !== null"
                                class="ml-1 text-xs text-gray-500"
                                >{{ role.share }}%</span
                            >
                        </span>
                        <span class="block text-xs text-gray-500">{{
                            role.use
                        }}</span>
                    </span>
                    <button
                        type="button"
                        :data-hex="role.hex"
                        class="font-mono text-sm text-gray-700 hover:text-gray-900"
                        :aria-label="`${role.hex} をコピー`"
                        @click="copy(role.hex, role.hex)"
                    >
                        {{ copied === role.hex ? 'コピーしました' : role.hex }}
                    </button>
                </li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-medium text-gray-700">
                読みやすさ（WCAG）
            </h2>
            <ul class="mt-2 space-y-1 text-sm">
                <li
                    v-for="check in checks"
                    :key="check.label"
                    class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-1.5"
                >
                    <span class="text-gray-700">{{ check.label }}</span>
                    <span class="font-mono text-gray-900"
                        >{{ check.ratio.toFixed(1) }}:1
                        <span
                            :class="[
                                'ml-2 rounded px-1.5 py-0.5 text-xs',
                                check.grade === '不適合'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-green-100 text-green-700',
                            ]"
                            >{{ check.grade }}</span
                        ></span
                    >
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="item in exports"
                :key="item.id"
                type="button"
                :data-export="item.id"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-gray-500"
                @click="copy(item.id, item.text)"
            >
                {{ copied === item.id ? 'コピーしました' : item.label }}
            </button>
        </div>
    </div>
</template>
