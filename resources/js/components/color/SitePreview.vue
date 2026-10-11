<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import {
    ensureReadable,
    isDarkColors,
    readableOn,
    toTheme,
} from '@/lib/palette/roles';
import type { PaletteColors } from '@/lib/palette/types';

const props = defineProps<{ colors: PaletteColors }>();

const mode = ref<'light' | 'dark'>(
    isDarkColors(props.colors) ? 'dark' : 'light',
);

watch(
    () => props.colors,
    (colors) => {
        mode.value = isDarkColors(colors) ? 'dark' : 'light';
    },
);

const theme = computed(() => toTheme(props.colors, mode.value));
const onMain = computed(() => readableOn(theme.value.main));
const onAccent = computed(() => readableOn(theme.value.accent));
// 淡いメイン・アクセントでも文字として読めるよう、見出し（大きい文字は 3:1）とリンク（4.5:1）の色だけ寄せる。
const heading = computed(() =>
    ensureReadable(theme.value.main, theme.value.base, 3),
);
const cardTitle = computed(() =>
    ensureReadable(theme.value.main, theme.value.sub, 3),
);
const link = computed(() =>
    ensureReadable(theme.value.accent, theme.value.sub, 4.5),
);
const adjusted = computed(
    () =>
        heading.value !== theme.value.main ||
        cardTitle.value !== theme.value.main ||
        link.value !== theme.value.accent,
);
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-medium text-gray-700">
                Webページに当てはめる
            </h2>
            <div
                class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs"
            >
                <button
                    type="button"
                    data-mode="light"
                    :aria-pressed="mode === 'light'"
                    :class="[
                        'px-3 py-1',
                        mode === 'light'
                            ? 'bg-gray-900 text-white'
                            : 'bg-white text-gray-700',
                    ]"
                    @click="mode = 'light'"
                >
                    ライト
                </button>
                <button
                    type="button"
                    data-mode="dark"
                    :aria-pressed="mode === 'dark'"
                    :class="[
                        'px-3 py-1',
                        mode === 'dark'
                            ? 'bg-gray-900 text-white'
                            : 'bg-white text-gray-700',
                    ]"
                    @click="mode = 'dark'"
                >
                    ダーク
                </button>
            </div>
        </div>

        <div
            data-preview="root"
            class="overflow-hidden rounded-lg border border-gray-200"
            :style="{ backgroundColor: theme.base, color: theme.text }"
        >
            <header
                data-preview="header"
                class="flex items-center justify-between px-5 py-3"
                :style="{ backgroundColor: theme.sub }"
            >
                <span class="font-bold" :style="{ color: cardTitle }"
                    >Sample</span
                >
                <nav class="flex gap-4 text-sm">
                    <span>機能</span>
                    <span>料金</span>
                    <span data-preview="link" :style="{ color: link }"
                        >お問い合わせ</span
                    >
                </nav>
            </header>

            <section data-preview="hero" class="px-5 py-10 text-center">
                <h3
                    data-preview="heading"
                    class="text-2xl font-bold"
                    :style="{ color: heading }"
                >
                    伝わるデザインを、すぐに
                </h3>
                <p class="mx-auto mt-3 max-w-md text-sm">
                    この配色をそのまま使って、読みやすく印象の良いページを作れます。
                </p>
                <div class="mt-5 flex justify-center gap-3">
                    <span
                        class="rounded-md px-5 py-2 text-sm font-medium"
                        :style="{ backgroundColor: theme.main, color: onMain }"
                        >はじめる</span
                    >
                    <span
                        class="rounded-md px-5 py-2 text-sm font-medium"
                        :style="{
                            backgroundColor: theme.accent,
                            color: onAccent,
                        }"
                        >詳しく見る</span
                    >
                </div>
            </section>

            <section class="grid gap-3 px-5 pb-6 sm:grid-cols-3">
                <div
                    v-for="n in 3"
                    :key="n"
                    data-preview="card"
                    class="rounded-lg p-4"
                    :style="{ backgroundColor: theme.sub }"
                >
                    <p
                        data-preview="card-title"
                        class="text-sm font-bold"
                        :style="{ color: cardTitle }"
                    >
                        特長 {{ n }}
                    </p>
                    <p class="mt-1 text-xs">
                        短い説明が入ります。文字の読みやすさを確認できます。
                    </p>
                    <span
                        class="mt-3 inline-block rounded px-2 py-0.5 text-xs"
                        :style="{
                            backgroundColor: theme.accent,
                            color: onAccent,
                        }"
                        >NEW</span
                    >
                </div>
            </section>

            <section
                data-preview="form"
                class="flex flex-wrap items-center gap-3 px-5 pb-6"
            >
                <span
                    class="min-w-0 flex-1 rounded-md px-3 py-2 text-sm opacity-80"
                    :style="{
                        backgroundColor: theme.sub,
                        border: `1px solid ${theme.main}`,
                    }"
                    >メールアドレス</span
                >
                <span
                    class="rounded-md px-4 py-2 text-sm font-medium"
                    :style="{ backgroundColor: theme.main, color: onMain }"
                    >登録する</span
                >
            </section>
        </div>
        <p
            v-if="adjusted"
            data-preview="note"
            class="mt-2 text-xs text-gray-500"
        >
            淡い色は文字に使うと読みにくいため、プレビューでは見出し・リンクの文字色だけ濃く（暗い背景では明るく）補正しています。書き出す色はそのままなので、文字に使うときは上の判定を確認してください。
        </p>
    </div>
</template>
