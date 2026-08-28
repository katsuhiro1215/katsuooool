<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { generateDummyData } from '@/lib/dummyData';
import type { DummyFieldType } from '@/lib/dummyData';

interface FieldOption {
    key: DummyFieldType;
    label: string;
}

const FIELD_OPTIONS: FieldOption[] = [
    { key: 'id', label: '連番ID' },
    { key: 'name', label: '氏名' },
    { key: 'email', label: 'メールアドレス' },
    { key: 'phone', label: '電話番号' },
    { key: 'address', label: '住所' },
    { key: 'company', label: '会社名' },
    { key: 'date', label: '日付' },
    { key: 'number', label: '数値' },
    { key: 'uuid', label: 'UUID' },
    { key: 'boolean', label: '真偽値' },
];

const selectedFields = ref<DummyFieldType[]>(['id', 'name', 'email']);
const count = ref(10);
const records = ref<Record<string, unknown>[]>([]);
const copied = ref(false);

const json = computed(() => JSON.stringify(records.value, null, 2));

const canGenerate = computed(
    () => selectedFields.value.length > 0 && count.value >= 1,
);

function toggleField(key: DummyFieldType) {
    if (selectedFields.value.includes(key)) {
        selectedFields.value = selectedFields.value.filter((f) => f !== key);
    } else {
        selectedFields.value = [...selectedFields.value, key];
    }
}

function generate() {
    if (!canGenerate.value) {
        return;
    }

    records.value = generateDummyData(selectedFields.value, count.value);

    axios.post('/generator-dummy-data/track').catch(() => {
        // Analytics-only endpoint; ignore failures.
    });
}

async function copy() {
    try {
        await navigator.clipboard.writeText(json.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}
</script>

<template>
    <Head title="ダミーデータ生成 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="ダミーデータ生成"
                description="フィールドと件数を指定して、JSON形式のダミーデータを生成します。"
            />

            <PageMain>
                <div>
                    <span class="text-sm font-medium text-gray-700"
                        >フィールド</span
                    >
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in FIELD_OPTIONS"
                            :key="option.key"
                            class="flex items-center gap-2 text-sm text-gray-700"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedFields.includes(option.key)"
                                @change="toggleField(option.key)"
                            />
                            {{ option.label }}
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-end gap-4">
                    <label class="text-sm text-gray-700">
                        件数
                        <input
                            v-model.number="count"
                            type="number"
                            min="1"
                            max="1000"
                            class="mt-1 block w-32 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                        />
                    </label>
                    <AppButton
                        type="button"
                        :disabled="!canGenerate"
                        @click="generate"
                    >
                        生成する
                    </AppButton>
                </div>

                <div v-if="records.length > 0" class="mt-8">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700"
                            >生成されたJSON</span
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
                        class="mt-2 max-h-96 overflow-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                        >{{ json }}</pre>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
