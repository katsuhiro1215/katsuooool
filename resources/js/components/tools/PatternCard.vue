<script setup lang="ts">
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import {
    PREVIEW_SIZE_MESSAGE,
    buildPatternDocument,
    fitScale,
} from '@/lib/uiPatternSnippet';

const props = defineProps<{
    id: number;
    title: string;
    locked: boolean;
    html?: string;
    css?: string;
    lockedSrcdoc?: string;
}>();

const MAX_PREVIEW_SIZE = 4000;

const activeTab = ref<'html' | 'css'>('html');
const frame = ref<HTMLIFrameElement | null>(null);
const box = ref<HTMLElement | null>(null);
// プレビュー内の部品の実寸。通知が来るまでは null（枠いっぱいに表示する従来の見え方）。
const contentSize = ref<{ width: number; height: number } | null>(null);
const boxSize = ref({ width: 0, height: 0 });
const copied = ref(false);

const srcdoc = computed(() =>
    props.locked
        ? (props.lockedSrcdoc ?? '')
        : buildPatternDocument(props.html ?? '', props.css ?? ''),
);

const activeCode = computed(() =>
    activeTab.value === 'html' ? (props.html ?? '') : (props.css ?? ''),
);

function measureBox() {
    if (box.value) {
        boxSize.value = {
            width: box.value.clientWidth,
            height: box.value.clientHeight,
        };
    }
}

function clampSize(value: unknown): number | null {
    if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) {
        return null;
    }

    return Math.min(MAX_PREVIEW_SIZE, Math.ceil(value));
}

// 自分の iframe からの通知だけを受け取る（sandbox に allow-same-origin が無いので、親の画面には触れない）。
function onMessage(event: MessageEvent) {
    if (!frame.value || event.source !== frame.value.contentWindow) {
        return;
    }

    const data = event.data as {
        type?: unknown;
        width?: unknown;
        height?: unknown;
    } | null;

    if (!data || data.type !== PREVIEW_SIZE_MESSAGE) {
        return;
    }

    const width = clampSize(data.width);
    const height = clampSize(data.height);

    if (width === null || height === null) {
        return;
    }

    measureBox();
    contentSize.value = { width, height };
}

const frameStyle = computed(() => {
    if (!contentSize.value) {
        return undefined;
    }

    const { width, height } = contentSize.value;
    const scale = fitScale(
        width,
        height,
        boxSize.value.width,
        boxSize.value.height,
    );

    return {
        width: `${width}px`,
        height: `${height}px`,
        position: 'absolute' as const,
        left: '50%',
        top: '50%',
        maxWidth: 'none',
        transform: `translate(-50%, -50%) scale(${scale})`,
    };
});

// 表示するドキュメントが変わったら、前のサイズは捨てる（新しいドキュメントが通知するまで枠いっぱいに表示）。
watch(srcdoc, () => {
    contentSize.value = null;
});

let boxObserver: ResizeObserver | null = null;

onMounted(() => {
    measureBox();

    // サイドバーの開閉やスクロールバーの出現など、ウィンドウ以外の理由で枠の幅が変わっても追従する。
    if (typeof ResizeObserver !== 'undefined' && box.value) {
        boxObserver = new ResizeObserver(measureBox);
        boxObserver.observe(box.value);
    }

    window.addEventListener('message', onMessage);
    window.addEventListener('resize', measureBox);
});

onBeforeUnmount(() => {
    boxObserver?.disconnect();
    window.removeEventListener('message', onMessage);
    window.removeEventListener('resize', measureBox);
});

async function copyCode() {
    try {
        await navigator.clipboard.writeText(activeCode.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);

        axios.post(route('ui-pattern-snippets.copy', props.id)).catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}
</script>

<template>
    <div class="relative rounded-lg border border-ink-900/10 bg-white">
        <span
            v-if="locked"
            class="absolute right-2 top-2 z-10 rounded-full bg-gradient-to-r from-gold-400 via-gold-300 to-gold-400 px-2 py-0.5 text-[10px] font-bold text-ink-900"
        >
            Premium
        </span>
        <div
            ref="box"
            class="relative flex h-56 items-center justify-center overflow-hidden border-b border-ink-900/10 bg-ink-900/5"
        >
            <iframe
                ref="frame"
                :srcdoc="srcdoc"
                sandbox="allow-scripts"
                :class="contentSize ? '' : 'h-full w-full'"
                :style="frameStyle"
                :title="`${title} のプレビュー`"
            />
        </div>
        <div class="p-4">
            <p class="mb-2 text-sm font-medium text-ink-900">{{ title }}</p>

            <div
                v-if="locked"
                class="rounded-md border border-ink-900/10 bg-ink-900/5 p-3 text-center text-xs text-ink-900/60"
            >
                <p>このパターンのコードはPremium会員限定です。</p>
                <a
                    href="/premium"
                    class="mt-2 inline-block font-medium text-ink-900 underline"
                >
                    Premiumに登録する
                </a>
            </div>
            <template v-else>
                <div class="mb-2 flex items-center justify-between">
                    <div class="flex gap-1">
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium"
                            :class="
                                activeTab === 'html'
                                    ? 'bg-ink-900 text-white'
                                    : 'text-ink-900/40 hover:bg-ink-900/10'
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
                                    ? 'bg-ink-900 text-white'
                                    : 'text-ink-900/40 hover:bg-ink-900/10'
                            "
                            @click="activeTab = 'css'"
                        >
                            CSS
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-md bg-ink-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-ink-700"
                        @click="copyCode"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="overflow-x-auto rounded-md bg-ink-900 p-3 text-xs text-white/90"
                    >{{ activeCode }}</pre>
            </template>
        </div>
    </div>
</template>
