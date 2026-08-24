<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import AppButton from '@/components/tools/AppButton.vue';
import { formatJson } from '@/lib/jsonFormat';

const input = ref('');
const output = ref('');
const errorMessage = ref('');
const copied = ref(false);

const canFormat = computed(() => input.value.trim() !== '');

function format() {
    if (!canFormat.value) {
        return;
    }

    const result = formatJson(input.value);

    if (result.success) {
        output.value = result.output;
        errorMessage.value = '';
    } else {
        output.value = '';
        errorMessage.value = result.error;
    }

    track();
}

async function copyOutput() {
    if (!output.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(output.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the output is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/json-format/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}
</script>

<template>
    <Head title="JSON Formatter - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    JSON Formatter
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    JSONを貼り付けると、構文チェックした上で読みやすい形式に整形します。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <label
                for="json-input"
                class="block text-sm font-medium text-gray-700"
                >JSON</label
            >
            <textarea
                id="json-input"
                v-model="input"
                rows="10"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs focus:border-gray-400 focus:outline-none focus:ring-0"
                placeholder='{"key": "value"}'
            />

            <AppButton
                type="button"
                class="mt-4 w-full"
                :disabled="!canFormat"
                @click="format"
            >
                整形する
            </AppButton>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                {{ errorMessage }}
            </p>

            <div v-if="output" class="mt-8">
                <div class="mb-3 flex justify-end">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:border-gray-400"
                        @click="copyOutput"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <textarea
                    :value="output"
                    rows="10"
                    readonly
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 font-mono text-xs text-gray-800"
                />
            </div>
        </main>
    </div>
</template>
