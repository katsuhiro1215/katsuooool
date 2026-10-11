import { describe, expect, test } from 'vitest';

import { contrastRatio, parseHex } from '@/lib/color';

import {
    VARIANT_COUNT,
    fromBase,
    generateAll,
    generatePalettes,
} from './generate';
import { MOODS } from './moods';
import { BASE_PATTERNS, HUE_FAMILIES } from './types';

const rgb = (hex: string) => parseHex(hex)!;
const ratio = (a: string, b: string) => contrastRatio(rgb(a), rgb(b));

describe('moods', () => {
    test('気分は 10 個で id が重複しない', () => {
        expect(MOODS).toHaveLength(10);
        expect(new Set(MOODS.map((m) => m.id)).size).toBe(10);
    });
});

describe('generatePalettes', () => {
    test('同じ入力は同じ結果になる', () => {
        expect(generatePalettes('gentle', 'blue')).toEqual(
            generatePalettes('gentle', 'blue'),
        );
    });

    test('気分に含まれない系統は空配列', () => {
        expect(generatePalettes('trust', 'red')).toEqual([]);
    });

    test('1 つの気分×系統あたり型の数だけ返し、id は <系統>-<番号>', () => {
        const palettes = generatePalettes('gentle', 'blue');
        expect(palettes).toHaveLength(VARIANT_COUNT);
        expect(palettes.map((p) => p.id)).toEqual(
            Array.from({ length: VARIANT_COUNT }, (_, i) => `blue-${i}`),
        );
    });

    test('全気分×全系統×全型で、5 色が重複せず、文字がベース・サブ背景の両方で 4.5:1 以上', () => {
        for (const mood of MOODS) {
            for (const family of HUE_FAMILIES) {
                for (const palette of generatePalettes(mood.id, family.id)) {
                    const [base, sub, , , text] = palette.colors;
                    const where = `${mood.id}/${palette.id}`;
                    expect(palette.colors, where).toHaveLength(5);
                    expect(new Set(palette.colors).size, where).toBe(5);
                    expect(ratio(text, base), where).toBeGreaterThanOrEqual(
                        4.5,
                    );
                    expect(ratio(text, sub), where).toBeGreaterThanOrEqual(4.5);
                }
            }
        }
    });
});

describe('generateAll', () => {
    test('気分に含まれる系統だけを行にする', () => {
        const groups = generateAll('trust');
        expect(groups.map((g) => g.family.id)).toEqual([
            'teal',
            'blue',
            'purple',
        ]);
        expect(groups.every((g) => g.palettes.length === VARIANT_COUNT)).toBe(
            true,
        );
    });
});

describe('fromBase', () => {
    test('メインに基準色そのものを使い、パターンごとに 1 つ返す', () => {
        for (const pattern of BASE_PATTERNS) {
            const palette = fromBase('#2f6f8f', pattern.id);
            expect(palette?.colors[2]).toBe('#2f6f8f');
            expect(palette?.id).toBe(pattern.id);
            expect(palette?.label).toBe(pattern.label);
        }
    });

    test('極端な基準色でも 5 色が揃い、文字が読める', () => {
        for (const hex of [
            '#000000',
            '#ffffff',
            '#ff0000',
            '#808080',
            '#00ff00',
        ]) {
            for (const pattern of BASE_PATTERNS) {
                const palette = fromBase(hex, pattern.id)!;
                const [base, sub, , , text] = palette.colors;
                expect(palette.colors, `${hex}/${pattern.id}`).toHaveLength(5);
                expect(
                    ratio(text, base),
                    `${hex}/${pattern.id}`,
                ).toBeGreaterThanOrEqual(4.5);
                expect(
                    ratio(text, sub),
                    `${hex}/${pattern.id}`,
                ).toBeGreaterThanOrEqual(4.5);
            }
        }
    });

    test('不正な hex は null', () => {
        expect(fromBase('zzz', 'analogous')).toBeNull();
    });
});
