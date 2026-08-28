<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

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

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="HTML Formatter"
                description="HTMLコードを貼り付けると、インデント付きの読みやすい形式に整形します。"
            />

            <PageMain>
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

                <AppButton
                    type="button"
                    class="mt-4 w-full"
                    :disabled="!canFormat"
                    @click="format"
                >
                    整形する
                </AppButton>

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
            </PageMain>
        </div>
    </PublicLayout>
</template>
