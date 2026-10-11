import { hexToHsl, hslToHex, parseHex, rgbToHex } from '@/lib/color';

import { getMood } from './moods';
import type { MoodDef } from './moods';
import { ensureReadable } from './roles';
import { BASE_PATTERNS, HUE_FAMILIES } from './types';
import type {
    BasePatternId,
    HueFamily,
    HueFamilyId,
    MoodId,
    Palette,
    PaletteColors,
} from './types';

/** メインの色相からアクセントをずらす角度（補色・類似色・分割補色・トライアド）。 */
const ACCENT_OFFSETS = [180, 30, 150, 120] as const;

export const VARIANT_COUNT = ACCENT_OFFSETS.length;

const normalize = (hue: number) => ((hue % 360) + 360) % 360;
const clamp = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value));

function build(
    hue: number,
    spec: Pick<MoodDef, 'main' | 'accent' | 'base' | 'dark'>,
    accentOffset: number,
): PaletteColors {
    const subL = spec.dark ? spec.base.l + 7 : spec.base.l - 6;
    const base = hslToHex({ h: hue, s: spec.base.s, l: spec.base.l });
    const sub = hslToHex({ h: hue, s: spec.base.s, l: clamp(subL, 0, 100) });
    const main = hslToHex({ h: hue, s: spec.main.s, l: spec.main.l });
    const accent = hslToHex({
        h: normalize(hue + accentOffset),
        s: spec.accent.s,
        l: spec.accent.l,
    });
    // サブ背景はベースより文字との差が小さい側なので、サブ背景に合わせれば両方で読める。
    const text = ensureReadable(
        hslToHex({ h: hue, s: 20, l: spec.dark ? 92 : 14 }),
        sub,
    );

    return [base, sub, main, accent, text];
}

export function generatePalettes(
    moodId: MoodId,
    familyId: HueFamilyId,
): Palette[] {
    const mood = getMood(moodId);
    const family = HUE_FAMILIES.find((f) => f.id === familyId);

    if (!family || !mood.families.includes(familyId)) {
        return [];
    }

    return ACCENT_OFFSETS.map((offset, variant) => ({
        id: `${familyId}-${variant}`,
        mood: moodId,
        colors: build(family.hue, mood, offset),
    }));
}

export function generateAll(
    moodId: MoodId,
): { family: HueFamily; palettes: Palette[] }[] {
    return HUE_FAMILIES.map((family) => ({
        family,
        palettes: generatePalettes(moodId, family.id),
    })).filter((group) => group.palettes.length > 0);
}

const BASE_SPEC = { s: 30, l: 96 } as const;

/** 彩度がこれ未満の基準色は無彩色（黒・白・灰）として扱い、色相を回しても変わらないのを補う。 */
const ACHROMATIC_SATURATION = 10;

function accentFor(
    hue: number,
    s: number,
    l: number,
    pattern: BasePatternId,
): { h: number; s: number; l: number } {
    const chromatic = s >= ACHROMATIC_SATURATION;
    const accentS = chromatic ? s : 60;
    const accentL = chromatic ? l : 50;

    switch (pattern) {
        case 'analogous':
            return { h: normalize(hue + 30), s: accentS, l: accentL };
        case 'complement':
            return { h: normalize(hue + 180), s: accentS, l: accentL };
        case 'split':
            return { h: normalize(hue + 150), s: accentS, l: accentL };
        case 'triad':
            return { h: normalize(hue + 120), s: accentS, l: accentL };
        case 'mono':
            return { h: hue, s, l: l > 50 ? l - 25 : l + 25 };
    }
}

/** 基準色をメインにして、パターンごとに配色を作る。不正な hex は null。 */
export function fromBase(hex: string, pattern: BasePatternId): Palette | null {
    const rgb = parseHex(hex);
    const hsl = hexToHsl(hex);

    if (!rgb || !hsl) {
        return null;
    }

    const main = rgbToHex(rgb);
    const accent = hslToHex(accentFor(hsl.h, hsl.s, hsl.l, pattern));
    // 灰色の基準色では、背景も無彩色のままにする。
    const baseS = Math.min(BASE_SPEC.s, hsl.s);
    const base = hslToHex({ h: hsl.h, s: baseS, l: BASE_SPEC.l });
    const sub = hslToHex({ h: hsl.h, s: baseS, l: BASE_SPEC.l - 6 });
    const text = ensureReadable(hslToHex({ h: hsl.h, s: 20, l: 14 }), sub);
    const label = BASE_PATTERNS.find((p) => p.id === pattern)?.label;

    return {
        id: pattern,
        label,
        mood: 'base',
        colors: [base, sub, main, accent, text],
    };
}
