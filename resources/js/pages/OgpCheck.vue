<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';

interface OgpCheckSuccess {
    status: 'success';
    tags: Record<string, string>;
    missing_recommended: string[];
}

interface OgpCheckFailure {
    status: 'failed';
    reason: string;
    message: string;
}

const url = ref('');
const isChecking = ref(false);
const errorMessage = ref('');
const result = ref<OgpCheckSuccess | null>(null);

const canCheck = computed(() => url.value.trim() !== '' && !isChecking.value);

async function check() {
    if (!canCheck.value) {
        return;
    }

    isChecking.value = true;
    errorMessage.value = '';
    result.value = null;

    try {
        const response = await axios.post<OgpCheckSuccess | OgpCheckFailure>(
            '/ogp-check',
            { url: url.value },
        );
        result.value = response.data as OgpCheckSuccess;
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
    <Head title="OGPチェック - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    OGPチェック
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    URLを入力すると、OGP・Twitter
                    Cardタグを取得してSNSシェアカードのプレビューを確認できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            <label
                for="url-input"
                class="block text-sm font-medium text-gray-700"
                >URL</label
            >
            <div class="mt-1 flex gap-2">
                <input
                    id="url-input"
                    v-model="url"
                    type="url"
                    placeholder="https://example.com/"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
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

            <div v-if="result" class="mt-8 space-y-6">
                <div
                    v-if="result.missing_recommended.length > 0"
                    class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    以下の推奨タグが見つかりませんでした:
                    {{ result.missing_recommended.join(', ') }}
                </div>

                <div
                    v-if="result.tags['og:image']"
                    class="overflow-hidden rounded-md border border-gray-200"
                >
                    <img
                        :src="result.tags['og:image']"
                        alt=""
                        class="h-48 w-full object-cover"
                    />
                    <div class="p-4">
                        <p class="font-medium text-gray-900">
                            {{ result.tags['og:title'] }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ result.tags['og:description'] }}
                        </p>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <tbody>
                        <tr
                            v-for="(value, key) in result.tags"
                            :key="key"
                            class="border-b border-gray-100"
                        >
                            <td
                                class="py-2 pr-4 font-mono text-xs text-gray-500"
                            >
                                {{ key }}
                            </td>
                            <td class="py-2 text-gray-800">{{ value }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
