<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PatternCard from '@/components/tools/PatternCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { buildFormCheck } from '@/lib/uiPatternGenerators';
import type { FormCheckType } from '@/lib/uiPatternGenerators';
import type { PatternSnippet } from '@/lib/uiPatternSnippet';

const props = defineProps<{ patterns: PatternSnippet[] }>();

const TYPE_OPTIONS: { key: FormCheckType; label: string }[] = [
    { key: 'checkbox', label: 'チェックボックス' },
    { key: 'radio', label: 'ラジオボタン' },
];

const type = ref<FormCheckType>('checkbox');
const label = ref('利用規約に同意する');
const checked = ref(true);
const disabled = ref(false);

const output = computed(() =>
    buildFormCheck({
        type: type.value,
        label: label.value,
        checked: checked.value,
        disabled: disabled.value,
    }),
);

const inputStyle = computed(() => ({
    width: '18px',
    height: '18px',
    borderRadius: type.value === 'radio' ? '50%' : '4px',
    accentColor: '#2563eb',
}));

const copiedHtml = ref(false);
const copiedCss = ref(false);

async function copyText(text: string, flag: 'html' | 'css') {
    try {
        await navigator.clipboard.writeText(text);

        if (flag === 'html') {
            copiedHtml.value = true;
            setTimeout(() => {
                copiedHtml.value = false;
            }, 1500);
        } else {
            copiedCss.value = true;
            setTimeout(() => {
                copiedCss.value = false;
            }, 1500);
        }
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([type, label, checked, disabled], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/ui-pattern-form-check/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="チェックボックス/ラジオボタン - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="チェックボックス/ラジオボタン"
                description="値を調整すると、チェックボックス/ラジオボタンのHTML/CSSコードがリアルタイムで生成されます。"
            />

            <PageMain>
                <div
                    class="mb-8 flex h-40 items-center rounded-md border border-gray-200 bg-white px-6"
                >
                    <label
                        class="inline-flex cursor-pointer items-center gap-2"
                    >
                        <input
                            :type="type"
                            :checked="checked"
                            :disabled="disabled"
                            :style="inputStyle"
                            class="cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <span class="text-sm">{{ label }}</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="text-sm text-gray-700">
                        タイプ
                        <select
                            v-model="type"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option
                                v-for="option in TYPE_OPTIONS"
                                :key="option.key"
                                :value="option.key"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-700">
                        ラベル
                        <input
                            v-model="label"
                            type="text"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                        />
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input v-model="checked" type="checkbox" />
                        選択状態(checked)
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-gray-700"
                    >
                        <input v-model="disabled" type="checkbox" />
                        無効化(disabled)
                    </label>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >HTML</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.html, 'html')"
                        >
                            {{ copiedHtml ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.html }}</pre>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >CSS</span
                        >
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                            @click="copyText(output.css, 'css')"
                        >
                            {{ copiedCss ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <pre
                        class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ output.css }}</pre>
                </div>

                <section v-if="props.patterns.length > 0" class="mt-16">
                    <h2 class="text-lg font-bold text-gray-900">
                        いろんなフォーム(チェックボックス)
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        気に入ったものをそのままコピーして使えます。
                    </p>
                    <div
                        class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <PatternCard
                            v-for="pattern in props.patterns"
                            :key="pattern.id"
                            :id="pattern.id"
                            :title="pattern.title"
                            :locked="pattern.locked"
                            :html="pattern.html"
                            :css="pattern.css"
                            :locked-srcdoc="pattern.srcdoc"
                        />
                    </div>
                </section>
            </PageMain>
        </div>
    </PublicLayout>
</template>
