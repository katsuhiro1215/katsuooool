<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildGrid } from '@/lib/cssGenerators';

const columns = ref(3);
const rows = ref(2);
const gap = ref(8);
const copied = ref(false);

const css = computed(() =>
    buildGrid({ columns: columns.value, rows: rows.value, gap: gap.value }),
);

const previewStyle = computed(() => ({
    display: 'grid',
    gridTemplateColumns: `repeat(${columns.value}, 1fr)`,
    gridTemplateRows: `repeat(${rows.value}, 1fr)`,
    gap: `${gap.value}px`,
}));

const cellCount = computed(() => columns.value * rows.value);

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

watch([columns, rows, gap], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-grid/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Grid ジェネレーター - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Grid ジェネレーター"
                description="列数・行数・gapを調整すると、下のプレビューに反映され、CSS Gridのコードが生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 min-h-40 rounded-md border border-ink-900/10 bg-white p-4"
                    :style="previewStyle"
                >
                    <div
                        v-for="n in cellCount"
                        :key="n"
                        class="flex items-center justify-center rounded-md bg-ink-900 text-sm font-medium text-white"
                    >
                        {{ n }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <label class="text-sm text-ink-900/70">
                        列数: {{ columns }}
                        <input
                            v-model.number="columns"
                            type="range"
                            min="1"
                            max="6"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
                        行数: {{ rows }}
                        <input
                            v-model.number="rows"
                            type="range"
                            min="1"
                            max="6"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="text-sm text-ink-900/70">
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
                        <span class="text-sm font-medium text-ink-900/70"
                            >生成されたCSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-ink-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-ink-700"
                            @click="copy"
                        >
                            {{ copied ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-ink-900 p-4 text-xs text-white/90"
                        >{{ css }}</pre>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
