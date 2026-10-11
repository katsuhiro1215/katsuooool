import { describe, expect, test } from 'vitest';

import { contrastRatio, parseHex } from '@/lib/color';

import {
    contrastChecks,
    contrastGrade,
    ensureReadable,
    isDarkColors,
    readableOn,
    toTheme,
} from './roles';
import type { PaletteColors } from './types';

const rgb = (hex: string) => parseHex(hex)!;
const ratio = (a: string, b: string) => contrastRatio(rgb(a), rgb(b));

describe('ensureReadable', () => {
    test('明るい背景では文字色を暗くして 4.5:1 を満たす', () => {
        const fg = ensureReadable('#aaaaaa', '#ffffff');
        expect(ratio(fg, '#ffffff')).toBeGreaterThanOrEqual(4.5);
    });

    test('暗い背景では文字色を明るくして 4.5:1 を満たす', () => {
        const fg = ensureReadable('#555555', '#101010');
        expect(ratio(fg, '#101010')).toBeGreaterThanOrEqual(4.5);
    });

    test('極端な背景でも必ず満たす（白・黒・中間色・純色）', () => {
        for (const bg of [
            '#000000',
            '#ffffff',
            '#808080',
            '#ff0000',
            '#00ff00',
            '#0000ff',
        ]) {
            const fg = ensureReadable('#777777', bg);
            expect(ratio(fg, bg)).toBeGreaterThanOrEqual(4.5);
        }
    });

    test('不正な hex はそのまま返す', () => {
        expect(ensureReadable('zzz', '#ffffff')).toBe('zzz');
    });
});

describe('readableOn', () => {
    test('どんな背景でも 4.5:1 以上になる白か黒を返す', () => {
        for (const bg of [
            '#000000',
            '#ffffff',
            '#808080',
            '#ff0000',
            '#ffff00',
            '#3366ff',
        ]) {
            expect(ratio(readableOn(bg), bg)).toBeGreaterThanOrEqual(4.5);
        }
    });
});

describe('contrastGrade', () => {
    test('AAA・AA・不適合を返す', () => {
        expect(contrastGrade(7)).toBe('AAA');
        expect(contrastGrade(4.5)).toBe('AA');
        expect(contrastGrade(4.49)).toBe('不適合');
    });
});

const LIGHT: PaletteColors = [
    '#f5f8ff',
    '#e6edfb',
    '#2f6f8f',
    '#e07a3f',
    '#1a2433',
];
const DARK: PaletteColors = [
    '#10151f',
    '#1a2130',
    '#7aa8e0',
    '#f0a35c',
    '#e8edf5',
];

describe('toTheme', () => {
    test('isDarkColors はベースの明るさで判定する', () => {
        expect(isDarkColors(LIGHT)).toBe(false);
        expect(isDarkColors(DARK)).toBe(true);
    });

    test('ライトのパレットを dark にしても文字が読める', () => {
        const theme = toTheme(LIGHT, 'dark');
        expect(ratio(theme.text, theme.base)).toBeGreaterThanOrEqual(4.5);
        expect(ratio(theme.text, theme.sub)).toBeGreaterThanOrEqual(4.5);
        expect(
            isDarkColors([
                theme.base,
                theme.sub,
                theme.main,
                theme.accent,
                theme.text,
            ]),
        ).toBe(true);
    });

    test('ダークのパレットを light にしても文字が読める', () => {
        const theme = toTheme(DARK, 'light');
        expect(ratio(theme.text, theme.base)).toBeGreaterThanOrEqual(4.5);
        expect(ratio(theme.text, theme.sub)).toBeGreaterThanOrEqual(4.5);
        expect(
            isDarkColors([
                theme.base,
                theme.sub,
                theme.main,
                theme.accent,
                theme.text,
            ]),
        ).toBe(false);
    });

    test('同じ向きならそのまま返す', () => {
        const theme = toTheme(LIGHT, 'light');
        expect(theme).toEqual({
            base: LIGHT[0],
            sub: LIGHT[1],
            main: LIGHT[2],
            accent: LIGHT[3],
            text: LIGHT[4],
        });
    });
});

describe('contrastChecks', () => {
    test('文字×ベース、文字×サブ背景、メイン・アクセント上のボタン文字を返す', () => {
        const checks = contrastChecks(LIGHT);
        expect(checks.map((c) => c.label)).toEqual([
            '文字 × ベース',
            '文字 × サブ背景',
            'ボタン文字 × メイン',
            'ボタン文字 × アクセント',
        ]);

        for (const check of checks) {
            expect(check.ratio).toBeGreaterThanOrEqual(4.5);
        }
    });
});
