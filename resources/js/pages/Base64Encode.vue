<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { decodeBase64, encodeBase64 } from '@/lib/textCodecs';

const input = ref('');
const output = ref('');
const errorMessage = ref('');
const copied = ref(false);

const canProcess = computed(() => input.value !== '');

function encode() {
    if (!canProcess.value) {
        return;
    }

    output.value = encodeBase64(input.value);
    errorMessage.value = '';
    track();
}

function decode() {
    if (!canProcess.value) {
        return;
    }

    const result = decodeBase64(input.value);

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
        axios.post('/base64-encode/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}
</script>

<template>
    <Head title="Base64 Encode/Decode - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Base64 Encode/Decode"
                description="文字列のBase64エンコード・デコードを相互に変換します。"
            />

            <PageMain>
                <label
                    for="base64-input"
                    class="block text-sm font-medium text-gray-700"
                    >入力文字列</label
                >
                <textarea
                    id="base64-input"
                    v-model="input"
                    rows="6"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs focus:border-gray-400 focus:outline-none focus:ring-0"
                />

                <div class="mt-4 flex gap-2">
                    <AppButton
                        type="button"
                        class="flex-1"
                        :disabled="!canProcess"
                        @click="encode"
                    >
                        エンコード
                    </AppButton>
                    <AppButton
                        type="button"
                        class="flex-1"
                        :disabled="!canProcess"
                        @click="decode"
                    >
                        デコード
                    </AppButton>
                </div>

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
                        rows="6"
                        readonly
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 font-mono text-xs text-gray-800"
                    />
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
