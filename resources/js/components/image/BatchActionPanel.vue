<script setup lang="ts">
import AppButton from '@/components/tools/AppButton.vue';
import ProcessingBanner from '@/components/tools/ProcessingBanner.vue';
import { formatBytes } from '@/lib/imageBatch';

defineProps<{
    phase: 'ready' | 'processing' | 'done';
    canSubmit: boolean;
    submitLabel: string;
    processingMessage: string;
    summary: {
        count: number;
        inputBytes: number;
        outputBytes: number;
        percent: number;
    } | null;
    zipUrl: string | null;
    countLabel: string;
}>();

const emit = defineEmits<{ submit: []; redo: []; clear: [] }>();
</script>

<template>
    <div class="space-y-4 rounded-xl border border-ink-900/10 bg-white p-4">
        <p class="text-xs text-ink-900/40">{{ countLabel }}</p>

        <template v-if="phase !== 'done'">
            <slot />

            <AppButton
                type="button"
                class="w-full"
                :disabled="!canSubmit || phase === 'processing'"
                :loading="phase === 'processing'"
                @click="emit('submit')"
            >
                {{ phase === 'processing' ? '処理しています...' : submitLabel }}
            </AppButton>

            <ProcessingBanner
                v-if="phase === 'processing'"
                :message="processingMessage"
            />
        </template>

        <template v-else>
            <div v-if="summary" class="text-sm text-ink-900/70">
                <p class="font-medium text-ink-900">{{ summary.count }}枚が完了しました</p>
                <p class="mt-1 text-xs text-ink-900/40">
                    合計 {{ formatBytes(summary.inputBytes) }} →
                    {{ formatBytes(summary.outputBytes) }}
                    <span v-if="summary.percent > 0" class="font-medium text-sprout-600"
                        >（{{ summary.percent }}%削減）</span
                    >
                </p>
            </div>

            <AppButton v-if="zipUrl" as="a" :href="zipUrl" class="w-full">
                まとめてダウンロード(ZIP)
            </AppButton>

            <button
                type="button"
                class="w-full rounded-md border border-ink-900/15 px-4 py-2 text-sm text-ink-900/70 hover:bg-ink-900/5"
                @click="emit('redo')"
            >
                設定を変えてやり直す
            </button>
            <button
                type="button"
                class="w-full text-sm text-ink-900/40 underline hover:text-ink-900"
                @click="emit('clear')"
            >
                すべて削除
            </button>
        </template>
    </div>
</template>
