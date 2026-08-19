<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import { formatHtml } from '@/lib/htmlFormat';

const input = ref('');
const output = ref('');
const copied = ref(false);

const canFormat = computed(() => input.value.trim() !== '');

function format() {
    if (!canFormat.value) {
        return;
    }

    output.value = formatHtml(input.value);
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
        axios.post('/html-format/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}
</script>

<template>
    <Head title="HTML Formatter - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    HTML Formatter
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    HTMLコードを貼り付けると、インデント付きの読みやすい形式に整形します。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <label
                for="html-input"
                class="block text-sm font-medium text-gray-700"
                >HTMLコード</label
            >
            <textarea
                id="html-input"
                v-model="input"
                rows="10"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs focus:border-gray-400 focus:outline-none focus:ring-0"
                placeholder="<div><p>hello</p></div>"
            />

            <button
                type="button"
                class="mt-4 w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                :disabled="!canFormat"
                @click="format"
            >
                整形する
            </button>

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
