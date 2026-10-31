<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

interface StructuredDataBlock {
    raw: string;
    valid: boolean;
    data?: unknown;
    error?: string;
    warnings: string[];
}

interface StructuredDataCheckSuccess {
    status: 'success';
    blocks: StructuredDataBlock[];
}

const url = ref('');
const isChecking = ref(false);
const errorMessage = ref('');
const result = ref<StructuredDataCheckSuccess | null>(null);

const canCheck = computed(() => url.value.trim() !== '' && !isChecking.value);

async function check() {
    if (!canCheck.value) {
        return;
    }

    isChecking.value = true;
    errorMessage.value = '';
    result.value = null;

    try {
        const response = await axios.post<StructuredDataCheckSuccess>(
            '/structured-data-check',
            { url: url.value },
        );
        result.value = response.data;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            errorMessage.value = error.response.data.message as string;
        } else {
            errorMessage.value = 'チェック中にエラーが発生しました。';
        }
    } finally {
        isChecking.value = false;
    }
}
</script>

<template>
    <Head title="構造化データ確認 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="構造化データ確認"
                description="URLを入力すると、ページ内のJSON-LD構造化データを取得・検証します。"
            />

            <PageMain>
                <label
                    for="url-input"
                    class="block text-sm font-medium text-ink-900/70"
                    >URL</label
                >
                <div class="mt-1 flex gap-2">
                    <input
                        id="url-input"
                        v-model="url"
                        type="url"
                        placeholder="https://example.com/"
                        class="w-full rounded-md border border-ink-900/20 px-3 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                    />
                    <AppButton
                        type="button"
                        class="shrink-0"
                        :disabled="!canCheck"
                        @click="check"
                    >
                        {{ isChecking ? 'チェック中...' : 'チェックする' }}
                    </AppButton>
                </div>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                    {{ errorMessage }}
                </p>

                <p
                    v-if="result && result.blocks.length === 0"
                    class="mt-8 text-sm text-ink-900/40"
                >
                    JSON-LD形式の構造化データは見つかりませんでした。
                </p>

                <div v-if="result" class="mt-8 space-y-4">
                    <div
                        v-for="(block, index) in result.blocks"
                        :key="index"
                        class="rounded-md border border-ink-900/10 p-4"
                    >
                        <p
                            class="text-sm font-medium"
                            :class="
                                block.valid ? 'text-green-600' : 'text-red-600'
                            "
                        >
                            {{
                                block.valid
                                    ? '有効なJSON-LD'
                                    : `構文エラー: ${block.error}`
                            }}
                        </p>
                        <ul
                            v-if="block.warnings.length > 0"
                            class="mt-2 list-inside list-disc text-sm text-amber-700"
                        >
                            <li
                                v-for="warning in block.warnings"
                                :key="warning"
                            >
                                {{ warning }}
                            </li>
                        </ul>
                        <pre
                            class="mt-3 overflow-x-auto rounded bg-ink-900/5 p-3 font-mono text-xs text-ink-900/80"
                            >{{ block.raw }}</pre>
                    </div>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
