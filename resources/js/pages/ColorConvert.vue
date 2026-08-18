<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';

import { hslToRgb, parseHex, rgbToHex, rgbToHsl } from '@/lib/color';
import type { Rgb } from '@/lib/color';

const INITIAL_RGB: Rgb = { r: 51, g: 102, b: 255 };

const rgb = ref<Rgb>({ ...INITIAL_RGB });
const hexInput = ref(rgbToHex(INITIAL_RGB));
const rInput = ref(String(INITIAL_RGB.r));
const gInput = ref(String(INITIAL_RGB.g));
const bInput = ref(String(INITIAL_RGB.b));
const hslDisplay = ref(rgbToHsl(INITIAL_RGB));
const copiedField = ref('');

let suppressWatchers = false;

function applyRgb(next: Rgb) {
    suppressWatchers = true;
    rgb.value = next;
    hexInput.value = rgbToHex(next);
    rInput.value = String(next.r);
    gInput.value = String(next.g);
    bInput.value = String(next.b);
    hslDisplay.value = rgbToHsl(next);
    suppressWatchers = false;
}

watch(hexInput, (value) => {
    if (suppressWatchers) {
        return;
    }

    const parsed = parseHex(value);

    if (parsed) {
        applyRgb(parsed);
        track();
    }
});

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
        applyRgb({ r, g, b });
        track();
    }
}

function onHslChannelChange(field: 'h' | 's' | 'l', value: string) {
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return;
    }

    const next = { ...hslDisplay.value, [field]: parsed };
    applyRgb(hslToRgb(next));
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

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    HEX/RGB/HSL変換
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    色を選択するか、いずれかの表記を入力すると他の表記がリアルタイムに更新されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="flex flex-col items-start gap-6 sm:flex-row">
                <input
                    :value="hexInput"
                    type="color"
                    class="h-32 w-32 cursor-pointer rounded-md border border-gray-200"
                    aria-label="カラーピッカー"
                    @input="
                        (e) => (hexInput = (e.target as HTMLInputElement).value)
                    "
                />

                <div class="w-full space-y-4">
                    <div>
                        <label
                            for="hex"
                            class="block text-sm font-medium text-gray-700"
                            >HEX</label
                        >
                        <div class="mt-1 flex gap-2">
                            <input
                                id="hex"
                                v-model="hexInput"
                                type="text"
                                class="w-40 rounded-md border border-gray-300 px-3 py-2 font-mono text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                            />
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:border-gray-400"
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
                        <span class="block text-sm font-medium text-gray-700"
                            >RGB</span
                        >
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <input
                                v-model="rInput"
                                type="number"
                                min="0"
                                max="255"
                                class="w-20 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                                @input="onRgbChannelChange"
                            />
                            <input
                                v-model="gInput"
                                type="number"
                                min="0"
                                max="255"
                                class="w-20 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                                @input="onRgbChannelChange"
                            />
                            <input
                                v-model="bInput"
                                type="number"
                                min="0"
                                max="255"
                                class="w-20 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                                @input="onRgbChannelChange"
                            />
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:border-gray-400"
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
                        <span class="block text-sm font-medium text-gray-700"
                            >HSL</span
                        >
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <input
                                :value="hslDisplay.h"
                                type="number"
                                min="0"
                                max="360"
                                class="w-20 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
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
                                :value="hslDisplay.s"
                                type="number"
                                min="0"
                                max="100"
                                class="w-20 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
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
                                :value="hslDisplay.l"
                                type="number"
                                min="0"
                                max="100"
                                class="w-20 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
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
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:border-gray-400"
                                @click="
                                    copy(
                                        'hsl',
                                        `hsl(${hslDisplay.h}, ${hslDisplay.s}%, ${hslDisplay.l}%)`,
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
        </main>
    </div>
</template>
