<script setup lang="ts">
import { computed, ref } from 'vue';

import { buildPatternDocument } from '@/lib/uiPatternSnippet';

const props = defineProps<{
    title: string;
    html: string;
    css: string;
}>();

const activeTab = ref<'html' | 'css'>('html');
const copied = ref(false);

const srcdoc = computed(() => buildPatternDocument(props.html, props.css));

const activeCode = computed(() =>
    activeTab.value === 'html' ? props.html : props.css,
);

async function copyCode() {
    try {
        await navigator.clipboard.writeText(activeCode.value);
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
    <div class="rounded-lg border border-gray-200 bg-white">
        <div
            class="flex h-40 items-center justify-center border-b border-gray-200 bg-gray-50"
        >
            <iframe
                :srcdoc="srcdoc"
                sandbox=""
                class="h-full w-full"
                title="プレビュー"
            />
        </div>
        <div class="p-4">
            <p class="mb-2 text-sm font-medium text-gray-900">{{ title }}</p>
            <div class="mb-2 flex items-center justify-between">
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="
                            activeTab === 'html'
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-500 hover:bg-gray-100'
                        "
                        @click="activeTab = 'html'"
                    >
                        HTML
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="
                            activeTab === 'css'
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-500 hover:bg-gray-100'
                        "
                        @click="activeTab = 'css'"
                    >
                        CSS
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                    @click="copyCode"
                >
                    {{ copied ? 'コピーしました' : 'コピー' }}
                </button>
            </div>
            <pre
                class="overflow-x-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100"
                >{{ activeCode }}</pre>
        </div>
    </div>
</template>
