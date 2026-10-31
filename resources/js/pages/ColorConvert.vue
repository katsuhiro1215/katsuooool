<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

import { ref, watch } from 'vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

import { hslToRgb, parseHex, rgbToHex, rgbToHsl } from '@/lib/color';
import type { Hsl, Rgb } from '@/lib/color';

const INITIAL_RGB: Rgb = { r: 51, g: 102, b: 255 };

const rgb = ref<Rgb>({ ...INITIAL_RGB });
const hexInput = ref(rgbToHex(INITIAL_RGB));
const rInput = ref(String(INITIAL_RGB.r));
const gInput = ref(String(INITIAL_RGB.g));
const bInput = ref(String(INITIAL_RGB.b));
// Kept as its own source of truth (not re-derived from rgb after every
// edit) so that editing H/S while L is 0 or 100 — or editing H while S is
// 0 — doesn't collapse back to whatever HSL rgbToHsl() reconstructs for a
// degenerate RGB, which silently discards the hue/saturation the user just
// typed.
const hsl = ref<Hsl>(rgbToHsl(INITIAL_RGB));
const copiedField = ref('');

// Guards against the hexInput watcher re-firing when applyFromRgb()
// normalizes the field programmatically. `flush: 'sync'` below makes the
// watcher run synchronously within this same guarded block instead of as a
// microtask, so the flag is still true when it would otherwise re-enter.
let suppressWatchers = false;

function applyFromRgb(next: Rgb) {
    suppressWatchers = true;
    rgb.value = next;
    hexInput.value = rgbToHex(next);
    rInput.value = String(next.r);
    gInput.value = String(next.g);
    bInput.value = String(next.b);
    hsl.value = rgbToHsl(next);
    suppressWatchers = false;
}

function applyFromHsl(next: Hsl) {
    suppressWatchers = true;
    hsl.value = next;
    const nextRgb = hslToRgb(next);
    rgb.value = nextRgb;
    hexInput.value = rgbToHex(nextRgb);
    rInput.value = String(nextRgb.r);
    gInput.value = String(nextRgb.g);
    bInput.value = String(nextRgb.b);
    suppressWatchers = false;
}

watch(
    hexInput,
    (value) => {
        if (suppressWatchers) {
            return;
        }

        const parsed = parseHex(value);

        if (parsed) {
            applyFromRgb(parsed);
            track();
        }
    },
    { flush: 'sync' },
);

function onRgbChannelChange() {
    if (suppressWatchers) {
        return;
    }

    const r = Number(rInput.value);
    const g = Number(gInput.value);
    const b = Number(bInput.value);

    if (
        Number.isInteger(r) &&
        Number.isInteger(g) &&
        Number.isInteger(b) &&
        r >= 0 &&
        r <= 255 &&
        g >= 0 &&
        g <= 255 &&
        b >= 0 &&
        b <= 255
    ) {
        applyFromRgb({ r, g, b });
        track();
    }
}

function onHslChannelChange(field: 'h' | 's' | 'l', value: string) {
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return;
    }

    applyFromHsl({ ...hsl.value, [field]: parsed });
    track();
}

async function copy(field: string, value: string) {
    try {
        await navigator.clipboard.writeText(value);
        copiedField.value = field;
        setTimeout(() => {
            if (copiedField.value === field) {
                copiedField.value = '';
            }
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

function track() {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/color-convert/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
}
</script>

<template>
    <Head title="HEX/RGB/HSL変換 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="HEX/RGB/HSL変換"
                description="色を選択するか、いずれかの表記を入力すると他の表記がリアルタイムに更新されます。"
            />

            <PageMain>
                <div class="flex flex-col items-start gap-6 sm:flex-row">
                    <input
                        :value="hexInput"
                        type="color"
                        class="h-32 w-32 cursor-pointer rounded-md border border-ink-900/10"
                        aria-label="カラーピッカー"
                        @input="
                            (e) =>
                                (hexInput = (e.target as HTMLInputElement)
                                    .value)
                        "
                    />

                    <div class="w-full space-y-4">
                        <div>
                            <label
                                for="hex"
                                class="block text-sm font-medium text-ink-900/70"
                                >HEX</label
                            >
                            <div class="mt-1 flex gap-2">
                                <input
                                    id="hex"
                                    v-model="hexInput"
                                    type="text"
                                    class="w-40 rounded-md border border-ink-900/20 px-3 py-2 font-mono text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                />
                                <button
                                    type="button"
                                    class="rounded-md border border-ink-900/20 px-3 py-2 text-sm text-ink-900/70 hover:border-ink-900/30"
                                    @click="copy('hex', hexInput)"
                                >
                                    {{
                                        copiedField === 'hex'
                                            ? 'コピーしました'
                                            : 'コピー'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <span
                                class="block text-sm font-medium text-ink-900/70"
                                >RGB</span
                            >
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <input
                                    v-model="rInput"
                                    type="number"
                                    min="0"
                                    max="255"
                                    aria-label="R"
                                    class="w-20 rounded-md border border-ink-900/20 px-2 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                    @input="onRgbChannelChange"
                                />
                                <input
                                    v-model="gInput"
                                    type="number"
                                    min="0"
                                    max="255"
                                    aria-label="G"
                                    class="w-20 rounded-md border border-ink-900/20 px-2 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                    @input="onRgbChannelChange"
                                />
                                <input
                                    v-model="bInput"
                                    type="number"
                                    min="0"
                                    max="255"
                                    aria-label="B"
                                    class="w-20 rounded-md border border-ink-900/20 px-2 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                    @input="onRgbChannelChange"
                                />
                                <button
                                    type="button"
                                    class="rounded-md border border-ink-900/20 px-3 py-2 text-sm text-ink-900/70 hover:border-ink-900/30"
                                    @click="
                                        copy(
                                            'rgb',
                                            `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`,
                                        )
                                    "
                                >
                                    {{
                                        copiedField === 'rgb'
                                            ? 'コピーしました'
                                            : 'コピー'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <span
                                class="block text-sm font-medium text-ink-900/70"
                                >HSL</span
                            >
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <input
                                    :value="hsl.h"
                                    type="number"
                                    min="0"
                                    max="360"
                                    aria-label="H"
                                    class="w-20 rounded-md border border-ink-900/20 px-2 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                    @input="
                                        (e) =>
                                            onHslChannelChange(
                                                'h',
                                                (e.target as HTMLInputElement)
                                                    .value,
                                            )
                                    "
                                />
                                <input
                                    :value="hsl.s"
                                    type="number"
                                    min="0"
                                    max="100"
                                    aria-label="S"
                                    class="w-20 rounded-md border border-ink-900/20 px-2 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                    @input="
                                        (e) =>
                                            onHslChannelChange(
                                                's',
                                                (e.target as HTMLInputElement)
                                                    .value,
                                            )
                                    "
                                />
                                <input
                                    :value="hsl.l"
                                    type="number"
                                    min="0"
                                    max="100"
                                    aria-label="L"
                                    class="w-20 rounded-md border border-ink-900/20 px-2 py-2 text-sm focus:border-ink-900/30 focus:outline-none focus:ring-0"
                                    @input="
                                        (e) =>
                                            onHslChannelChange(
                                                'l',
                                                (e.target as HTMLInputElement)
                                                    .value,
                                            )
                                    "
                                />
                                <button
                                    type="button"
                                    class="rounded-md border border-ink-900/20 px-3 py-2 text-sm text-ink-900/70 hover:border-ink-900/30"
                                    @click="
                                        copy(
                                            'hsl',
                                            `hsl(${hsl.h}, ${hsl.s}%, ${hsl.l}%)`,
                                        )
                                    "
                                >
                                    {{
                                        copiedField === 'hsl'
                                            ? 'コピーしました'
                                            : 'コピー'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
