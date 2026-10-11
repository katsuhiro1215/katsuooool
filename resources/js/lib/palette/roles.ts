import {
    contrastRatio,
    hexToHsl,
    hslToHex,
    parseHex,
    relativeLuminance,
} from '@/lib/color';

import type { PaletteColors, RoleKey } from './types';

/** 役割の表示名・使いどころ・割合の目安（文字色は割合なし）。 */
export const ROLE_INFO: Record<
    RoleKey,
    { label: string; use: string; share: number | null }
> = {
    base: { label: 'ベース', use: '背景', share: 60 },
    sub: { label: 'サブ背景', use: 'カード・区切り', share: 10 },
    main: { label: 'メイン', use: '見出し・ボタン・ロゴ', share: 25 },
    accent: { label: 'アクセント', use: '強調・リンク・バッジ', share: 5 },
    text: { label: '文字', use: '本文', share: null },
};

/** 輝度がこの値未満の背景は「暗い」とみなす（白と黒の 4.5:1 の分かれ目）。 */
const DARK_LUMINANCE = 0.18;

/**
 * 文字色 `fg` を、背景 `bg` に対して `min`:1 以上になるまで明るさだけ動かす。
 * 動かしても届かない場合は白か黒に倒す。`fg` が不正な hex ならそのまま返す。
 */
export function ensureReadable(fg: string, bg: string, min = 4.5): string {
    const bgRgb = parseHex(bg);
    const hsl = hexToHsl(fg);

    if (!bgRgb || !hsl) {
        return fg;
    }

    const lighten = relativeLuminance(bgRgb) < DARK_LUMINANCE;
    let l = hsl.l;

    while (l >= 0 && l <= 100) {
        const hex = hslToHex({ ...hsl, l });
        const rgb = parseHex(hex);

        if (rgb && contrastRatio(rgb, bgRgb) >= min) {
            return hex;
        }

        l += lighten ? 1 : -1;
    }

    return lighten ? '#ffffff' : '#000000';
}

/** 背景 `bg` の上に置く、白か黒のうち読みやすい方（ボタン文字用）。 */
export function readableOn(bg: string): string {
    const rgb = parseHex(bg);

    if (!rgb) {
        return '#111111';
    }

    const white = contrastRatio({ r: 255, g: 255, b: 255 }, rgb);
    const black = contrastRatio({ r: 17, g: 17, b: 17 }, rgb);

    return white >= black ? '#ffffff' : '#111111';
}

export function contrastGrade(ratio: number): 'AAA' | 'AA' | '不適合' {
    if (ratio >= 7) {
        return 'AAA';
    }

    return ratio >= 4.5 ? 'AA' : '不適合';
}

export function isDarkColors(colors: PaletteColors): boolean {
    const base = parseHex(colors[0]);

    return base ? relativeLuminance(base) < DARK_LUMINANCE : false;
}

export interface Theme {
    base: string;
    sub: string;
    main: string;
    accent: string;
    text: string;
}

function withLightness(hex: string, pick: (l: number) => number): string {
    const hsl = hexToHsl(hex);

    return hsl ? hslToHex({ ...hsl, l: pick(hsl.l) }) : hex;
}

/**
 * パレットをライト／ダークのどちらかで見るための 5 色。
 * パレット自身の向きと同じならそのまま、逆なら背景・文字を作り直し、
 * メイン・アクセントは明るさだけを寄せる。
 */
export function toTheme(colors: PaletteColors, mode: 'light' | 'dark'): Theme {
    const [base, sub, main, accent, text] = colors;

    if (isDarkColors(colors) === (mode === 'dark')) {
        return { base, sub, main, accent, text };
    }

    const h = hexToHsl(main)?.h ?? 0;
    const s = Math.min(30, Math.max(10, hexToHsl(base)?.s ?? 20));

    if (mode === 'dark') {
        const newBase = hslToHex({ h, s, l: 10 });
        const newSub = hslToHex({ h, s, l: 17 });

        return {
            base: newBase,
            sub: newSub,
            main: withLightness(main, (l) => Math.max(l, 60)),
            accent: withLightness(accent, (l) => Math.max(l, 62)),
            text: ensureReadable(hslToHex({ h, s: 20, l: 92 }), newSub),
        };
    }

    const newBase = hslToHex({ h, s: 30, l: 96 });
    const newSub = hslToHex({ h, s: 30, l: 90 });

    return {
        base: newBase,
        sub: newSub,
        main: withLightness(main, (l) => Math.min(l, 45)),
        accent: withLightness(accent, (l) => Math.min(l, 48)),
        text: ensureReadable(hslToHex({ h, s: 20, l: 14 }), newSub),
    };
}

export interface ContrastCheck {
    label: string;
    ratio: number;
    grade: 'AAA' | 'AA' | '不適合';
}

function check(label: string, a: string, b: string): ContrastCheck {
    const ra = parseHex(a);
    const rb = parseHex(b);
    const ratio = ra && rb ? contrastRatio(ra, rb) : 1;

    return { label, ratio, grade: contrastGrade(ratio) };
}

export function contrastChecks(colors: PaletteColors): ContrastCheck[] {
    const [base, sub, main, accent, text] = colors;

    return [
        check('文字 × ベース', text, base),
        check('文字 × サブ背景', text, sub),
        check('ボタン文字 × メイン', readableOn(main), main),
        check('ボタン文字 × アクセント', readableOn(accent), accent),
    ];
}
