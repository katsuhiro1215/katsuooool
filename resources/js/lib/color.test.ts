import { describe, expect, test } from 'vitest';

import {
    contrastRatio,
    hexToHsl,
    hslToHex,
    hslToRgb,
    parseHex,
    relativeLuminance,
    rgbToHex,
    rgbToHsl,
    rotateHue,
} from './color';

describe('parseHex', () => {
    test('parses a 6-digit hex color with #', () => {
        expect(parseHex('#3366ff')).toEqual({ r: 51, g: 102, b: 255 });
    });

    test('parses a 6-digit hex color without #', () => {
        expect(parseHex('3366ff')).toEqual({ r: 51, g: 102, b: 255 });
    });

    test('rejects malformed input', () => {
        expect(parseHex('#12345')).toBeNull();
        expect(parseHex('#gggggg')).toBeNull();
        expect(parseHex('')).toBeNull();
    });
});

describe('rgbToHsl', () => {
    test('matches known reference values', () => {
        expect(rgbToHsl({ r: 255, g: 0, b: 0 })).toEqual({
            h: 0,
            s: 100,
            l: 50,
        });
        expect(rgbToHsl({ r: 0, g: 255, b: 0 })).toEqual({
            h: 120,
            s: 100,
            l: 50,
        });
        expect(rgbToHsl({ r: 30, g: 144, b: 255 })).toEqual({
            h: 210,
            s: 100,
            l: 56,
        });
    });

    test('handles achromatic black and white without dividing by zero', () => {
        expect(rgbToHsl({ r: 0, g: 0, b: 0 })).toEqual({ h: 0, s: 0, l: 0 });
        expect(rgbToHsl({ r: 255, g: 255, b: 255 })).toEqual({
            h: 0,
            s: 0,
            l: 100,
        });
    });
});

describe('hslToRgb', () => {
    test('reconstructs primaries at every 60-degree boundary', () => {
        expect(hslToRgb({ h: 0, s: 100, l: 50 })).toEqual({
            r: 255,
            g: 0,
            b: 0,
        });
        expect(hslToRgb({ h: 60, s: 100, l: 50 })).toEqual({
            r: 255,
            g: 255,
            b: 0,
        });
        expect(hslToRgb({ h: 120, s: 100, l: 50 })).toEqual({
            r: 0,
            g: 255,
            b: 0,
        });
        expect(hslToRgb({ h: 180, s: 100, l: 50 })).toEqual({
            r: 0,
            g: 255,
            b: 255,
        });
        expect(hslToRgb({ h: 240, s: 100, l: 50 })).toEqual({
            r: 0,
            g: 0,
            b: 255,
        });
        expect(hslToRgb({ h: 300, s: 100, l: 50 })).toEqual({
            r: 255,
            g: 0,
            b: 255,
        });
    });
});

describe('relativeLuminance / contrastRatio (WCAG 2.1)', () => {
    test('black vs white is the maximum 21:1', () => {
        const black = { r: 0, g: 0, b: 0 };
        const white = { r: 255, g: 255, b: 255 };
        expect(contrastRatio(black, white)).toBeCloseTo(21, 5);
    });

    test('#767676 on white is the well-known ~4.54:1 AA boundary', () => {
        const gray = parseHex('#767676')!;
        const white = { r: 255, g: 255, b: 255 };
        expect(contrastRatio(gray, white)).toBeCloseTo(4.542, 2);
    });

    test('relativeLuminance of pure white is 1', () => {
        expect(relativeLuminance({ r: 255, g: 255, b: 255 })).toBeCloseTo(1, 5);
    });
});

describe('hexToHsl / hslToHex round trip', () => {
    test('is stable across repeated round trips', () => {
        let hex = '#3366ff';

        for (let i = 0; i < 50; i++) {
            hex = hslToHex(hexToHsl(hex)!);
        }

        expect(hex).toBe('#3366ff');
    });
});

describe('rotateHue', () => {
    test('a 0-degree rotation is always a true identity', () => {
        // Regression test for the Phase 6 review finding: routing even a
        // zero-degree rotation through the integer-rounded HSL round-trip
        // shifted some colors by a few units (89.4% of a random sample).
        const samples = [
            '#02d8e6',
            '#3366ff',
            '#000000',
            '#ffffff',
            '#808080',
            '#f5f307',
        ];

        for (const hex of samples) {
            expect(rotateHue(hex, 0)).toBe(hex);
        }
    });

    test('360 and -360 degree rotations are also identities', () => {
        expect(rotateHue('#3366ff', 360)).toBe('#3366ff');
        expect(rotateHue('#3366ff', -360)).toBe('#3366ff');
    });

    test('180-degree rotation produces the expected complementary color', () => {
        expect(rotateHue('#3366ff', 180)).toBe('#ffcc33');
    });

    test('rotating an achromatic color is a no-op', () => {
        expect(rotateHue('#808080', 90)).toBe('#808080');
    });
});

describe('rgbToHex', () => {
    test('clamps out-of-range channel values', () => {
        expect(rgbToHex({ r: 300, g: -10, b: 128 })).toBe('#ff0080');
    });
});
