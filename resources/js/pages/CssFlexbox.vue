<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildFlexbox } from '@/lib/cssGenerators';
import type {
    AlignItems,
    FlexDirection,
    FlexWrap,
    JustifyContent,
} from '@/lib/cssGenerators';

const direction = ref<FlexDirection>('row');
const justifyContent = ref<JustifyContent>('flex-start');
const alignItems = ref<AlignItems>('stretch');
const wrap = ref<FlexWrap>('nowrap');
const gap = ref(8);
const copied = ref(false);

const css = computed(() =>
    buildFlexbox({
        direction: direction.value,
        justifyContent: justifyContent.value,
        alignItems: alignItems.value,
        wrap: wrap.value,
        gap: gap.value,
    }),
);

const previewStyle = computed(() => ({
    display: 'flex',
    flexDirection: direction.value,
    justifyContent: justifyContent.value,
    alignItems: alignItems.value,
    flexWrap: wrap.value,
    gap: `${gap.value}px`,
}));

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([direction, justifyContent, alignItems, wrap, gap], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-flexbox/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Flexbox ジェネレーター - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Flexbox ジェネレーター"
                description="プロパティを調整すると、下のプレビューが実際に並び替わり、CSSコードが生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 min-h-40 rounded-md border border-gray-200 bg-white p-4"
                    :style="previewStyle"
                >
                    <div
                        v-for="n in 4"
                        :key="n"
                        class="flex h-16 w-16 items-center justify-center rounded-md bg-gray-900 text-sm font-medium text-white"
                    >
                        {{ n }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700">
                        flex-direction
                        <select
                            v-model="direction"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="row">row</option>
                            <option value="row-reverse">row-reverse</option>
                            <option value="column">column</option>
                            <option value="column-reverse">
                                column-reverse
                            </option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700">
                        justify-content
                        <select
                            v-model="justifyContent"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="flex-start">flex-start</option>
                            <option value="flex-end">flex-end</option>
                            <option value="center">center</option>
                            <option value="space-between">space-between</option>
                            <option value="space-around">space-around</option>
                            <option value="space-evenly">space-evenly</option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700">
                        align-items
                        <select
                            v-model="alignItems"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="stretch">stretch</option>
                            <option value="flex-start">flex-start</option>
                            <option value="flex-end">flex-end</option>
                            <option value="center">center</option>
                            <option value="baseline">baseline</option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700">
                        flex-wrap
                        <select
                            v-model="wrap"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="nowrap">nowrap</option>
                            <option value="wrap">wrap</option>
                            <option value="wrap-reverse">wrap-reverse</option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700 sm:col-span-2">
                        gap: {{ gap }}px
                        <input
                            v-model.number="gap"
                            type="range"
                            min="0"
                            max="50"
                            class="mt-1 w-full"
                        />
                    </label>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >生成されたCSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copy"
                        >
                            {{ copied ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ css }}</pre>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
