<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { decodeUrl, encodeUrl } from '@/lib/textCodecs';

const input = ref('');
const output = ref('');
const errorMessage = ref('');
const copied = ref(false);

const canProcess = computed(() => input.value !== '');

function encode() {
    if (!canProcess.value) {
        return;
    }

    output.value = encodeUrl(input.value);
    errorMessage.value = '';
    track();
}

function decode() {
    if (!canProcess.value) {
        return;
    }

    const result = decodeUrl(input.value);

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
        axios.post('/url-encode/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}
</script>

<template>
    <Head title="URL Encode/Decode - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="URL Encode/Decode"
                description="文字列のURLエンコード・デコードを相互に変換します。"
            />

            <PageMain>
                <label
                    for="url-input"
                    class="block text-sm font-medium text-ink-900/70"
                    >入力文字列</label
                >
                <textarea
                    id="url-input"
                    v-model="input"
                    rows="6"
                    class="mt-1 w-full rounded-md border border-ink-900/20 px-3 py-2 font-mono text-xs focus:border-ink-900/30 focus:outline-none focus:ring-0"
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
                            class="rounded-md border border-ink-900/20 px-3 py-1.5 text-sm text-ink-900/70 hover:border-ink-900/30"
                            @click="copyOutput"
                        >
                            {{ copied ? 'コピーしました' : 'コピー' }}
                        </button>
                    </div>
                    <textarea
                        :value="output"
                        rows="6"
                        readonly
                        class="w-full rounded-md border border-ink-900/10 bg-white px-3 py-2 font-mono text-xs text-ink-900/80"
                    />
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
