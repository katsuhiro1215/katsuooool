<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, reactive, ref, watch } from 'vue';

import MoodSelector from '@/components/color/MoodSelector.vue';
import PaletteDetail from '@/components/color/PaletteDetail.vue';
import PaletteFamilyList from '@/components/color/PaletteFamilyList.vue';
import SitePreview from '@/components/color/SitePreview.vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { fromBase, generateAll } from '@/lib/palette/generate';
import { BASE_PATTERNS } from '@/lib/palette/types';
import type { MoodId, Palette } from '@/lib/palette/types';
import { parseState, serializeState } from '@/lib/palette/urlState';
import type { GeneratorState } from '@/lib/palette/urlState';

const state = reactive<GeneratorState>(
    parseState(typeof window === 'undefined' ? '' : window.location.search),
);

const groups = computed(() => {
    if (state.mode === 'base') {
        const palettes = BASE_PATTERNS.map((pattern) =>
            fromBase(state.base, pattern.id),
        ).filter((palette): palette is Palette => palette !== null);

        return [{ key: 'base', label: '基準色からの配色', palettes }];
    }

    return generateAll(state.mood).map((group) => ({
        key: group.family.id,
        label: group.family.label,
        palettes: group.palettes,
    }));
});

const allPalettes = computed(() => groups.value.flatMap((g) => g.palettes));

// 存在しない id が URL に入っていても、先頭のパレットを使う。
const selected = computed<Palette | undefined>(
    () =>
        allPalettes.value.find((palette) => palette.id === state.selected) ??
        allPalettes.value[0],
);

const detail = ref<HTMLElement | null>(null);

/** 1 列に積まれる幅（xl 未満）では、選んだ結果が画面の外になるので、詳細まで動かす。 */
function selectPalette(id: string) {
    state.selected = id;

    if (window.innerWidth < 1280) {
        nextTick(() =>
            detail.value?.scrollIntoView?.({
                behavior: 'smooth',
                block: 'start',
            }),
        );
    }
}

function selectMood(id: MoodId) {
    state.mode = 'mood';
    state.mood = id;
    state.selected = null;
}

function selectBase(hex: string) {
    state.mode = 'base';
    state.base = hex;
    state.selected = null;
}

watch(
    () => ({ ...state }),
    (value) => {
        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}${serializeState(value)}`,
        );
    },
);

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/color-generator/track').catch(() => {
            // 計測専用。失敗は無視する。
        });
    }, 500);
}

watch(() => [state.mode, state.mood, state.base], track);
</script>

<template>
    <Head title="配色ジェネレーター - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="配色ジェネレーター"
                description="気分を選んでパレットを見つけ、役割とコントラストを確認し、Webページに当てはめて、コードでコピーできます。"
            />

            <PageMain>
                <MoodSelector
                    :mode="state.mode"
                    :mood="state.mood"
                    :base="state.base"
                    @select-mood="selectMood"
                    @select-base="selectBase"
                />

                <div
                    class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
                >
                    <PaletteFamilyList
                        :groups="groups"
                        :selected-id="selected?.id ?? null"
                        @select="selectPalette"
                    />

                    <div v-if="selected" ref="detail" class="space-y-8">
                        <PaletteDetail :palette="selected" />
                        <SitePreview :colors="selected.colors" />
                    </div>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
