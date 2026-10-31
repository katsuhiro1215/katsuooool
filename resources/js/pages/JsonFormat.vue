<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

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

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="JSON Formatter"
                description="JSONを貼り付けると、構文チェックした上で読みやすい形式に整形します。"
            />

            <PageMain>
                <label
                    for="json-input"
                    class="block text-sm font-medium text-ink-900/70"
                    >JSON</label
                >
                <textarea
                    id="json-input"
                    v-model="input"
                    rows="10"
                    class="mt-1 w-full rounded-md border border-ink-900/20 px-3 py-2 font-mono text-xs focus:border-ink-900/30 focus:outline-none focus:ring-0"
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
                            class="rounded-md border border-ink-900/20 px-3 py-1.5 text-sm text-ink-900/70 hover:border-ink-900/30"
                            @click="copyOutput"
                        >
                            {{ copied ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <textarea
                        :value="output"
                        rows="10"
                        readonly
                        class="w-full rounded-md border border-ink-900/10 bg-white px-3 py-2 font-mono text-xs text-ink-900/80"
                    />
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
