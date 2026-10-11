# M8 配色ジェネレーター 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 気分を選ぶと色相系統ごとのパレットが並び、役割・コントラスト・Web ページのひな形・コードのコピーまで一画面でできる「配色ジェネレーター」（`/color-generator`）を作り、旧「色の組み合わせ」「カラーパレット生成」を統合する。

**Architecture:** 生成ロジックは `resources/js/lib/palette/` の純粋関数（乱数なし、同じ入力は同じ結果）。画面は薄いページ `ColorGenerator.vue` と `components/color/` の部品。状態は URL（`?mood=…&p=…` または `?base=…&p=…`）に `history.replaceState` で反映する。バックエンドは `TrackedToolController` を継承するだけで、DB・外部 API は使わない。

**Tech Stack:** Laravel 13 / Inertia v2 / Vue 3 / TypeScript / Tailwind、Vitest、Pest。既存の `resources/js/lib/color.ts`（`hslToHex`・`hexToHsl`・`parseHex`・`contrastRatio`・`relativeLuminance`）を再利用する。

**Spec:** `docs/superpowers/specs/2026-10-12-m8-color-generator-design.md`

## Global Constraints

- ドキュメント・コメントは日本語。
- 外部 API・LLM・乱数・DB は使わない（生成は同じ入力で同じ結果）。
- 気分は 10 個：やさしい／あたたかい／信頼・誠実／爽やか／力強い／上品・大人／元気・ポップ／自然・ナチュラル／モダン・クール／ダーク。
- 色相系統は 8 つ：赤・橙・黄・緑・青緑・青・紫・桃。1 パレットは 5 色（ベース・サブ背景・メイン・アクセント・文字）。
- 文字色は、サブ背景とベースの両方に対して 4.5:1 以上（WCAG AA）。保証できないときは白か黒に倒す。
- 割合の目安：ベース 60／メイン 25／サブ背景 10／アクセント 5。
- URL：`?mood=<id>&p=<系統>-<番号>`、基準色モードは `?base=<6桁hex>&p=<パターンid>`。不正な値は既定（やさしい）に戻す。
- 旧 `/color-scheme`・`/color-palette` の GET は 301 で `/color-generator` へ。旧 POST（track／examples copy）の route は消す。`ColorExample` のモデル・DB・`ColorGradient` の例ギャラリーは触らない。
- 本番反映：`app`・`scheduler`・`katsuooool-web` の 3 つを再作成（DB・Dockerfile・Caddy は変更なし）。

## Review Focus

- 基準色に極端な色（`#000000`、`#ffffff`、純色 `#ff0000`）を入れても、文字×背景が 4.5:1 以上で、5 色がエラーにならない → Task 1。
- 全気分×全系統×全型で、5 色が重複しない → Task 1。
- 暗いパレットをライト表示、明るいパレットをダーク表示にしても、文字が読める → Task 1（`toTheme`）。
- URL に未知の気分・系統・パターンや不正な hex、存在しない `p` が入っていても、既定に戻って画面が崩れない → Task 2、Task 5。
- クリップボードが使えなくてもエラーにならない → Task 4。

## ファイル構成

| ファイル | 役割 |
|---|---|
| `resources/js/lib/palette/types.ts`（新規） | 型と定数（役割キー、系統、パターン、割合） |
| `resources/js/lib/palette/moods.ts`（新規） | 10 個の気分の定義 |
| `resources/js/lib/palette/roles.ts`（新規） | 読みやすさの補正、テーマ（ライト／ダーク）、コントラスト判定、割合 |
| `resources/js/lib/palette/generate.ts`（新規） | 気分×系統のパレット生成、基準色からの生成 |
| `resources/js/lib/palette/export.ts`（新規） | CSS 変数・Tailwind・HEX 一覧の文字列 |
| `resources/js/lib/palette/urlState.ts`（新規） | URL ⇄ 状態 |
| `resources/js/components/color/MoodSelector.vue`・`PaletteCard.vue`・`PaletteFamilyList.vue`・`PaletteDetail.vue`・`SitePreview.vue`（新規） | 画面の部品（各 `.test.ts` 付き） |
| `resources/js/pages/ColorGenerator.vue`（新規） | 薄いページ |
| `app/Http/Controllers/Tools/ColorGeneratorController.php`（新規） | `TrackedToolController` を継承 |
| `routes/web.php`、`config/tools.php`（変更） | 新 route・ツール定義・旧 URL の 301 |
| 旧 `ColorScheme.vue`・`ColorPalette.vue`・`ColorSchemeController.php`・`ColorPaletteController.php`・各 Pest テスト（削除） | 統合により廃止 |

---

### Task 1: 生成ロジックの土台（型・気分・役割・生成）

**Files:**
- Create: `resources/js/lib/palette/types.ts`、`moods.ts`、`roles.ts`、`generate.ts`
- Test: `resources/js/lib/palette/roles.test.ts`、`generate.test.ts`

**Interfaces:**
- Produces（以降のタスクが使う）:
  - `types.ts`：`HueFamilyId`、`HueFamily {id,label,hue}`、`HUE_FAMILIES`、`MoodId`、`PaletteColors = [string,string,string,string,string]`（順に base・sub・main・accent・text）、`ROLE_KEYS`、`Palette {id:string; label?:string; mood:MoodId|'base'; colors:PaletteColors}`、`BasePatternId`、`BASE_PATTERNS`
  - `moods.ts`：`MoodDef`、`MOODS`、`DEFAULT_MOOD`、`isMoodId(v): v is MoodId`、`getMood(id)`
  - `roles.ts`：`ensureReadable(fg,bg,min?)`、`readableOn(bg)`、`contrastGrade(ratio)`、`isDarkColors(colors)`、`Theme`、`toTheme(colors, mode)`、`contrastChecks(colors)`、`ROLE_INFO`
  - `generate.ts`：`VARIANT_COUNT`、`generatePalettes(moodId, familyId)`、`generateAll(moodId)`、`fromBase(hex, patternId)`

- [ ] **Step 1: 型と定数を書く**

`resources/js/lib/palette/types.ts`:

```ts
export type HueFamilyId =
    | 'red'
    | 'orange'
    | 'yellow'
    | 'green'
    | 'teal'
    | 'blue'
    | 'purple'
    | 'pink';

export interface HueFamily {
    id: HueFamilyId;
    label: string;
    hue: number;
}

export const HUE_FAMILIES: HueFamily[] = [
    { id: 'red', label: '赤系', hue: 2 },
    { id: 'orange', label: 'オレンジ系', hue: 28 },
    { id: 'yellow', label: '黄系', hue: 48 },
    { id: 'green', label: '緑系', hue: 130 },
    { id: 'teal', label: '青緑系', hue: 172 },
    { id: 'blue', label: '青系', hue: 215 },
    { id: 'purple', label: '紫系', hue: 268 },
    { id: 'pink', label: 'ピンク系', hue: 330 },
];

export type MoodId =
    | 'gentle'
    | 'warm'
    | 'trust'
    | 'fresh'
    | 'strong'
    | 'elegant'
    | 'pop'
    | 'natural'
    | 'modern'
    | 'dark';

/** 順に ベース（背景）・サブ背景・メイン・アクセント・文字。 */
export type PaletteColors = [string, string, string, string, string];

export const ROLE_KEYS = ['base', 'sub', 'main', 'accent', 'text'] as const;
export type RoleKey = (typeof ROLE_KEYS)[number];

export interface Palette {
    /** 気分モードは `<系統>-<番号>`、基準色モードはパターン id。 */
    id: string;
    /** 基準色モードの表示名（類似色など）。 */
    label?: string;
    mood: MoodId | 'base';
    colors: PaletteColors;
}

export type BasePatternId =
    | 'analogous'
    | 'complement'
    | 'split'
    | 'triad'
    | 'mono';

export const BASE_PATTERNS: { id: BasePatternId; label: string }[] = [
    { id: 'analogous', label: '類似色' },
    { id: 'complement', label: '補色' },
    { id: 'split', label: '分割補色' },
    { id: 'triad', label: 'トライアド' },
    { id: 'mono', label: '同系色' },
];
```

`resources/js/lib/palette/moods.ts`:

```ts
import type { HueFamilyId, MoodId } from './types';

export interface HslSpec {
    s: number;
    l: number;
}

export interface MoodDef {
    id: MoodId;
    label: string;
    hint: string;
    /** この気分で行を出す色相系統。 */
    families: HueFamilyId[];
    dark: boolean;
    main: HslSpec;
    accent: HslSpec;
    base: HslSpec;
}

const ALL: HueFamilyId[] = [
    'red',
    'orange',
    'yellow',
    'green',
    'teal',
    'blue',
    'purple',
    'pink',
];

export const MOODS: MoodDef[] = [
    {
        id: 'gentle',
        label: 'やさしい',
        hint: '低彩度・明るいパステル',
        families: ALL,
        dark: false,
        main: { s: 45, l: 72 },
        accent: { s: 50, l: 68 },
        base: { s: 45, l: 96 },
    },
    {
        id: 'warm',
        label: 'あたたかい',
        hint: '赤〜黄の暖色',
        families: ['red', 'orange', 'yellow', 'pink'],
        dark: false,
        main: { s: 65, l: 52 },
        accent: { s: 70, l: 48 },
        base: { s: 60, l: 95 },
    },
    {
        id: 'trust',
        label: '信頼・誠実',
        hint: '青〜青緑の落ち着いた色',
        families: ['teal', 'blue', 'purple'],
        dark: false,
        main: { s: 55, l: 36 },
        accent: { s: 60, l: 48 },
        base: { s: 30, l: 97 },
    },
    {
        id: 'fresh',
        label: '爽やか',
        hint: '青緑〜緑の明るい色',
        families: ['green', 'teal', 'blue'],
        dark: false,
        main: { s: 60, l: 44 },
        accent: { s: 65, l: 52 },
        base: { s: 50, l: 96 },
    },
    {
        id: 'strong',
        label: '力強い',
        hint: '高彩度でコントラスト大',
        families: ALL,
        dark: false,
        main: { s: 80, l: 40 },
        accent: { s: 90, l: 50 },
        base: { s: 10, l: 96 },
    },
    {
        id: 'elegant',
        label: '上品・大人',
        hint: '低彩度のくすみ色',
        families: ALL,
        dark: false,
        main: { s: 18, l: 36 },
        accent: { s: 32, l: 48 },
        base: { s: 12, l: 94 },
    },
    {
        id: 'pop',
        label: '元気・ポップ',
        hint: '高彩度で複数の色相',
        families: ALL,
        dark: false,
        main: { s: 90, l: 55 },
        accent: { s: 95, l: 58 },
        base: { s: 90, l: 96 },
    },
    {
        id: 'natural',
        label: '自然・ナチュラル',
        hint: '緑〜茶の穏やかな色',
        families: ['green', 'yellow', 'orange', 'teal'],
        dark: false,
        main: { s: 30, l: 36 },
        accent: { s: 42, l: 44 },
        base: { s: 25, l: 94 },
    },
    {
        id: 'modern',
        label: 'モダン・クール',
        hint: '無彩色寄り＋アクセント1色',
        families: ALL,
        dark: false,
        main: { s: 6, l: 22 },
        accent: { s: 80, l: 50 },
        base: { s: 4, l: 97 },
    },
    {
        id: 'dark',
        label: 'ダーク',
        hint: '暗い背景＋明るいアクセント',
        families: ALL,
        dark: true,
        main: { s: 55, l: 62 },
        accent: { s: 80, l: 62 },
        base: { s: 25, l: 10 },
    },
];

export const DEFAULT_MOOD: MoodId = 'gentle';

export function isMoodId(value: string | null | undefined): value is MoodId {
    return MOODS.some((mood) => mood.id === value);
}

export function getMood(id: MoodId): MoodDef {
    return MOODS.find((mood) => mood.id === id) ?? MOODS[0];
}
```

- [ ] **Step 2: 役割・読みやすさ・テーマの失敗するテストを書く**

`resources/js/lib/palette/roles.test.ts`:

```ts
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
        for (const bg of ['#000000', '#ffffff', '#808080', '#ff0000', '#00ff00', '#0000ff']) {
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
        for (const bg of ['#000000', '#ffffff', '#808080', '#ff0000', '#ffff00', '#3366ff']) {
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

const LIGHT: PaletteColors = ['#f5f8ff', '#e6edfb', '#2f6f8f', '#e07a3f', '#1a2433'];
const DARK: PaletteColors = ['#10151f', '#1a2130', '#7aa8e0', '#f0a35c', '#e8edf5'];

describe('toTheme', () => {
    test('isDarkColors はベースの明るさで判定する', () => {
        expect(isDarkColors(LIGHT)).toBe(false);
        expect(isDarkColors(DARK)).toBe(true);
    });

    test('ライトのパレットを dark にしても文字が読める', () => {
        const theme = toTheme(LIGHT, 'dark');
        expect(ratio(theme.text, theme.base)).toBeGreaterThanOrEqual(4.5);
        expect(ratio(theme.text, theme.sub)).toBeGreaterThanOrEqual(4.5);
        expect(isDarkColors([theme.base, theme.sub, theme.main, theme.accent, theme.text])).toBe(true);
    });

    test('ダークのパレットを light にしても文字が読める', () => {
        const theme = toTheme(DARK, 'light');
        expect(ratio(theme.text, theme.base)).toBeGreaterThanOrEqual(4.5);
        expect(ratio(theme.text, theme.sub)).toBeGreaterThanOrEqual(4.5);
        expect(isDarkColors([theme.base, theme.sub, theme.main, theme.accent, theme.text])).toBe(false);
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
```

- [ ] **Step 3: テストが失敗することを確認する**

Run: `npx vitest run resources/js/lib/palette/roles.test.ts`
Expected: FAIL（`./roles` が存在しない）

- [ ] **Step 4: `roles.ts` を実装する**

`resources/js/lib/palette/roles.ts`:

```ts
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
```

- [ ] **Step 5: テストが通ることを確認する**

Run: `npx vitest run resources/js/lib/palette/roles.test.ts`
Expected: PASS

- [ ] **Step 6: 生成ロジックの失敗するテストを書く**

`resources/js/lib/palette/generate.test.ts`:

```ts
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
                    expect(ratio(text, base), where).toBeGreaterThanOrEqual(4.5);
                    expect(ratio(text, sub), where).toBeGreaterThanOrEqual(4.5);
                }
            }
        }
    });
});

describe('generateAll', () => {
    test('気分に含まれる系統だけを行にする', () => {
        const groups = generateAll('trust');
        expect(groups.map((g) => g.family.id)).toEqual(['teal', 'blue', 'purple']);
        expect(groups.every((g) => g.palettes.length === VARIANT_COUNT)).toBe(true);
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
        for (const hex of ['#000000', '#ffffff', '#ff0000', '#808080', '#00ff00']) {
            for (const pattern of BASE_PATTERNS) {
                const palette = fromBase(hex, pattern.id)!;
                const [base, sub, , , text] = palette.colors;
                expect(palette.colors, `${hex}/${pattern.id}`).toHaveLength(5);
                expect(ratio(text, base), `${hex}/${pattern.id}`).toBeGreaterThanOrEqual(4.5);
                expect(ratio(text, sub), `${hex}/${pattern.id}`).toBeGreaterThanOrEqual(4.5);
            }
        }
    });

    test('不正な hex は null', () => {
        expect(fromBase('zzz', 'analogous')).toBeNull();
    });
});
```

- [ ] **Step 7: テストが失敗することを確認する**

Run: `npx vitest run resources/js/lib/palette/generate.test.ts`
Expected: FAIL（`./generate` が存在しない）

- [ ] **Step 8: `generate.ts` を実装する**

`resources/js/lib/palette/generate.ts`:

```ts
import { hexToHsl, hslToHex, parseHex, rgbToHex } from '@/lib/color';

import { getMood, type MoodDef } from './moods';
import { ensureReadable } from './roles';
import {
    BASE_PATTERNS,
    HUE_FAMILIES,
    type BasePatternId,
    type HueFamily,
    type HueFamilyId,
    type MoodId,
    type Palette,
    type PaletteColors,
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

const BASE_SPEC = {
    dark: false,
    base: { s: 30, l: 96 },
} as const;

function accentFor(
    hue: number,
    mainS: number,
    mainL: number,
    pattern: BasePatternId,
): { h: number; s: number; l: number } {
    switch (pattern) {
        case 'analogous':
            return { h: normalize(hue + 30), s: mainS, l: mainL };
        case 'complement':
            return { h: normalize(hue + 180), s: mainS, l: mainL };
        case 'split':
            return { h: normalize(hue + 150), s: mainS, l: mainL };
        case 'triad':
            return { h: normalize(hue + 120), s: mainS, l: mainL };
        case 'mono':
            return { h: hue, s: mainS, l: mainL > 50 ? mainL - 25 : mainL + 25 };
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
    const base = hslToHex({ h: hsl.h, ...BASE_SPEC.base });
    const sub = hslToHex({ h: hsl.h, s: BASE_SPEC.base.s, l: BASE_SPEC.base.l - 6 });
    const text = ensureReadable(hslToHex({ h: hsl.h, s: 20, l: 14 }), sub);
    const label = BASE_PATTERNS.find((p) => p.id === pattern)?.label;

    return {
        id: pattern,
        label,
        mood: 'base',
        colors: [base, sub, main, accent, text],
    };
}
```

- [ ] **Step 9: テストが通ることを確認する**

Run: `npx vitest run resources/js/lib/palette`
Expected: PASS。もし「5 色が重複しない」が特定の気分で落ちたら、`moods.ts` のその気分のメイン／アクセントの値を少しずらして重複を解消する（コンストラクタの意図は変えない）。基準色が `#000000` や `#ffffff` で `main` とアクセントが同一になる場合は、`mono` の `l` 分岐で必ず差が出るようにする（今の式で差は出る）。重複の検査は `fromBase` の極端値テストには含めていない（メインに基準色そのものを使うため、`#ffffff` ではベースと近くなりうるが、重複は許容）。

- [ ] **Step 10: 型チェックと Lint を通してコミットする**

Run: `npm run types:check && npx eslint resources/js/lib/palette`
Expected: エラーなし

```bash
git add resources/js/lib/palette
git commit -m "feat(m8): 配色の生成ロジック（気分・役割・読みやすさ・基準色）

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2: 書き出しと URL 状態

**Files:**
- Create: `resources/js/lib/palette/export.ts`、`urlState.ts`
- Test: `resources/js/lib/palette/export.test.ts`、`urlState.test.ts`

**Interfaces:**
- Consumes: `PaletteColors`、`ROLE_KEYS`、`MoodId`、`BasePatternId`、`BASE_PATTERNS`（Task 1）、`DEFAULT_MOOD`・`isMoodId`（Task 1）
- Produces:
  - `export.ts`：`toCssVariables(colors): string`、`toTailwind(colors): string`、`toHexList(colors): string`
  - `urlState.ts`：`GeneratorState { mode: 'mood'|'base'; mood: MoodId; base: string; selected: string|null }`、`DEFAULT_BASE`（`#2f6f8f`）、`parseState(search: string): GeneratorState`、`serializeState(state): string`（`?` 付き）

- [ ] **Step 1: 書き出しの失敗するテストを書く**

`resources/js/lib/palette/export.test.ts`:

```ts
import { expect, test } from 'vitest';

import { toCssVariables, toHexList, toTailwind } from './export';
import type { PaletteColors } from './types';

const COLORS: PaletteColors = ['#f5f8ff', '#e6edfb', '#2f6f8f', '#e07a3f', '#1a2433'];

test('CSS 変数は :root に --color-* で 5 行並ぶ', () => {
    expect(toCssVariables(COLORS)).toBe(
        [
            ':root {',
            '  --color-base: #f5f8ff;',
            '  --color-sub: #e6edfb;',
            '  --color-main: #2f6f8f;',
            '  --color-accent: #e07a3f;',
            '  --color-text: #1a2433;',
            '}',
        ].join('\n'),
    );
});

test('Tailwind は theme.extend.colors に役割名で入る', () => {
    const out = toTailwind(COLORS);
    expect(out).toContain('extend: {');
    expect(out).toContain("main: '#2f6f8f',");
    expect(out).toContain("text: '#1a2433',");
});

test('HEX 一覧は役割名つきで 5 行', () => {
    expect(toHexList(COLORS).split('\n')).toEqual([
        'ベース #f5f8ff',
        'サブ背景 #e6edfb',
        'メイン #2f6f8f',
        'アクセント #e07a3f',
        '文字 #1a2433',
    ]);
});
```

`resources/js/lib/palette/urlState.test.ts`:

```ts
import { describe, expect, test } from 'vitest';

import { DEFAULT_BASE, parseState, serializeState } from './urlState';

describe('parseState', () => {
    test('空なら既定（やさしい・気分モード）', () => {
        expect(parseState('')).toEqual({
            mode: 'mood',
            mood: 'gentle',
            base: DEFAULT_BASE,
            selected: null,
        });
    });

    test('気分と選択を読む', () => {
        const state = parseState('?mood=trust&p=blue-2');
        expect(state.mode).toBe('mood');
        expect(state.mood).toBe('trust');
        expect(state.selected).toBe('blue-2');
    });

    test('base があれば基準色モード（# 無し・大文字も許容）', () => {
        const state = parseState('?base=2F6F8F&p=triad');
        expect(state.mode).toBe('base');
        expect(state.base).toBe('#2f6f8f');
        expect(state.selected).toBe('triad');
    });

    test('未知の気分・不正な hex・不正な p は既定に戻る', () => {
        expect(parseState('?mood=zzz').mood).toBe('gentle');
        expect(parseState('?base=xyz').mode).toBe('mood');
        expect(parseState('?base=12345').mode).toBe('mood');
        expect(parseState('?p=<script>').selected).toBeNull();
        expect(parseState('?p=' + 'a'.repeat(50)).selected).toBeNull();
    });
});

describe('serializeState', () => {
    test('気分モード', () => {
        expect(
            serializeState({ mode: 'mood', mood: 'warm', base: DEFAULT_BASE, selected: 'red-1' }),
        ).toBe('?mood=warm&p=red-1');
    });

    test('選択なしなら p を付けない', () => {
        expect(
            serializeState({ mode: 'mood', mood: 'warm', base: DEFAULT_BASE, selected: null }),
        ).toBe('?mood=warm');
    });

    test('基準色モードは # を外す', () => {
        expect(
            serializeState({ mode: 'base', mood: 'gentle', base: '#2f6f8f', selected: 'mono' }),
        ).toBe('?base=2f6f8f&p=mono');
    });

    test('parse と serialize は往復できる', () => {
        const state = parseState('?base=ff0000&p=split');
        expect(parseState(serializeState(state))).toEqual(state);
    });
});
```

- [ ] **Step 2: 失敗を確認する**

Run: `npx vitest run resources/js/lib/palette/export.test.ts resources/js/lib/palette/urlState.test.ts`
Expected: FAIL（モジュールが無い）

- [ ] **Step 3: 実装する**

`resources/js/lib/palette/export.ts`:

```ts
import { ROLE_INFO } from './roles';
import { ROLE_KEYS, type PaletteColors } from './types';

/** `--color-*` の名前にしておくと、Tailwind v4 の `@theme` にもそのまま貼れる。 */
export function toCssVariables(colors: PaletteColors): string {
    const lines = ROLE_KEYS.map(
        (key, index) => `  --color-${key}: ${colors[index]};`,
    );

    return [':root {', ...lines, '}'].join('\n');
}

export function toTailwind(colors: PaletteColors): string {
    const lines = ROLE_KEYS.map(
        (key, index) => `        ${key}: '${colors[index]}',`,
    );

    return [
        '// tailwind.config.js',
        'export default {',
        '  theme: {',
        '    extend: {',
        '      colors: {',
        ...lines.map((line) => `  ${line}`),
        '      },',
        '    },',
        '  },',
        '};',
    ].join('\n');
}

export function toHexList(colors: PaletteColors): string {
    return ROLE_KEYS.map(
        (key, index) => `${ROLE_INFO[key].label} ${colors[index]}`,
    ).join('\n');
}
```

`resources/js/lib/palette/urlState.ts`:

```ts
import { DEFAULT_MOOD, isMoodId } from './moods';
import type { MoodId } from './types';

export const DEFAULT_BASE = '#2f6f8f';

export interface GeneratorState {
    mode: 'mood' | 'base';
    mood: MoodId;
    base: string;
    /** 選択中のパレット id。未選択（先頭を使う）は null。 */
    selected: string | null;
}

const SELECTED_PATTERN = /^[a-z]{1,12}(-\d{1,2})?$/;
const HEX6 = /^[0-9a-f]{6}$/;

export function parseState(search: string): GeneratorState {
    const query = new URLSearchParams(search);
    const baseParam = (query.get('base') ?? '').replace(/^#/, '').toLowerCase();
    const hasBase = HEX6.test(baseParam);
    const moodParam = query.get('mood');
    const selected = query.get('p');

    return {
        mode: hasBase ? 'base' : 'mood',
        mood: isMoodId(moodParam) ? moodParam : DEFAULT_MOOD,
        base: hasBase ? `#${baseParam}` : DEFAULT_BASE,
        selected: selected && SELECTED_PATTERN.test(selected) ? selected : null,
    };
}

export function serializeState(state: GeneratorState): string {
    const query = new URLSearchParams();

    if (state.mode === 'base') {
        query.set('base', state.base.replace(/^#/, ''));
    } else {
        query.set('mood', state.mood);
    }

    if (state.selected) {
        query.set('p', state.selected);
    }

    return `?${query.toString()}`;
}
```

- [ ] **Step 4: 通ることを確認する**

Run: `npx vitest run resources/js/lib/palette`
Expected: PASS（全件）

- [ ] **Step 5: 型・Lint・コミット**

Run: `npm run types:check && npx eslint resources/js/lib/palette`

```bash
git add resources/js/lib/palette
git commit -m "feat(m8): パレットの書き出しと URL 状態

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3: 選ぶ側の部品（気分・カード・一覧）

**Files:**
- Create: `resources/js/components/color/MoodSelector.vue`、`PaletteCard.vue`、`PaletteFamilyList.vue`
- Test: 同ディレクトリの `MoodSelector.test.ts`、`PaletteCard.test.ts`、`PaletteFamilyList.test.ts`

**Interfaces:**
- Consumes: `MOODS`、`MoodDef`（moods.ts）、`Palette`、`MoodId`（types.ts）
- Produces:
  - `MoodSelector`：props `mode: 'mood'|'base'`、`mood: MoodId`、`base: string`；emits `select-mood(id: MoodId)`、`select-base(hex: string)`
  - `PaletteCard`：props `palette: Palette`、`selected: boolean`；emits `select`
  - `PaletteFamilyList`：props `groups: { key: string; label: string; palettes: Palette[] }[]`、`selectedId: string | null`；emits `select(id: string)`

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/components/color/PaletteCard.test.ts`:

```ts
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import type { Palette } from '@/lib/palette/types';

import PaletteCard from './PaletteCard.vue';

const palette: Palette = {
    id: 'blue-0',
    mood: 'gentle',
    colors: ['#f5f8ff', '#e6edfb', '#2f6f8f', '#e07a3f', '#1a2433'],
};

test('5 色の帯を描き、押すと select を出す', async () => {
    const wrapper = mount(PaletteCard, { props: { palette, selected: false } });

    expect(wrapper.findAll('[data-swatch]')).toHaveLength(5);
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('select')).toHaveLength(1);
});

test('選択中は aria-pressed が true', () => {
    const wrapper = mount(PaletteCard, { props: { palette, selected: true } });
    expect(wrapper.find('button').attributes('aria-pressed')).toBe('true');
});

test('label があればカードの下に表示する', () => {
    const wrapper = mount(PaletteCard, {
        props: { palette: { ...palette, label: '補色' }, selected: false },
    });
    expect(wrapper.text()).toContain('補色');
});
```

`resources/js/components/color/PaletteFamilyList.test.ts`:

```ts
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import type { Palette } from '@/lib/palette/types';

import PaletteFamilyList from './PaletteFamilyList.vue';

const make = (id: string): Palette => ({
    id,
    mood: 'gentle',
    colors: ['#f5f8ff', '#e6edfb', '#2f6f8f', '#e07a3f', '#1a2433'],
});

const groups = [
    { key: 'red', label: '赤系', palettes: [make('red-0'), make('red-1')] },
    { key: 'blue', label: '青系', palettes: [make('blue-0')] },
];

test('系統ごとに見出しとカードを出す', () => {
    const wrapper = mount(PaletteFamilyList, { props: { groups, selectedId: null } });

    expect(wrapper.text()).toContain('赤系');
    expect(wrapper.text()).toContain('青系');
    expect(wrapper.findAll('button[aria-pressed]')).toHaveLength(3);
});

test('選択中のカードだけ aria-pressed が true', () => {
    const wrapper = mount(PaletteFamilyList, { props: { groups, selectedId: 'blue-0' } });
    const pressed = wrapper.findAll('button[aria-pressed="true"]');
    expect(pressed).toHaveLength(1);
});

test('カードを押すとそのパレット id で select を出す', async () => {
    const wrapper = mount(PaletteFamilyList, { props: { groups, selectedId: null } });
    await wrapper.findAll('button[aria-pressed]')[1].trigger('click');
    expect(wrapper.emitted('select')?.[0]).toEqual(['red-1']);
});
```

`resources/js/components/color/MoodSelector.test.ts`:

```ts
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import MoodSelector from './MoodSelector.vue';

test('気分ボタンを 10 個出し、押すと select-mood を出す', async () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'mood', mood: 'gentle', base: '#2f6f8f' },
    });

    const buttons = wrapper.findAll('[data-mood]');
    expect(buttons).toHaveLength(10);

    await wrapper.find('[data-mood="trust"]').trigger('click');
    expect(wrapper.emitted('select-mood')?.[0]).toEqual(['trust']);
});

test('気分モードでは現在の気分だけ aria-pressed が true', () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'mood', mood: 'warm', base: '#2f6f8f' },
    });

    expect(wrapper.find('[data-mood="warm"]').attributes('aria-pressed')).toBe('true');
    expect(wrapper.find('[data-mood="gentle"]').attributes('aria-pressed')).toBe('false');
    expect(wrapper.find('[data-base-toggle]').attributes('aria-pressed')).toBe('false');
});

test('基準色モードでは気分が押された状態にならず、基準色の入力が出る', () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'base', mood: 'warm', base: '#2f6f8f' },
    });

    expect(wrapper.find('[data-mood="warm"]').attributes('aria-pressed')).toBe('false');
    expect(wrapper.find('[data-base-toggle]').attributes('aria-pressed')).toBe('true');
    expect(wrapper.find('input[type="color"]').exists()).toBe(true);
});

test('基準色を変えると select-base を出す', async () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'base', mood: 'gentle', base: '#2f6f8f' },
    });

    await wrapper.find('input[type="color"]').setValue('#ff0000');
    expect(wrapper.emitted('select-base')?.[0]).toEqual(['#ff0000']);
});

test('「基準色から作る」を押すと現在の基準色で select-base を出す', async () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'mood', mood: 'gentle', base: '#2f6f8f' },
    });

    await wrapper.find('[data-base-toggle]').trigger('click');
    expect(wrapper.emitted('select-base')?.[0]).toEqual(['#2f6f8f']);
});
```

- [ ] **Step 2: 失敗を確認する**

Run: `npx vitest run resources/js/components/color`
Expected: FAIL（コンポーネントが無い）

- [ ] **Step 3: 実装する**

`resources/js/components/color/PaletteCard.vue`:

```vue
<script setup lang="ts">
import type { Palette } from '@/lib/palette/types';

defineProps<{ palette: Palette; selected: boolean }>();
defineEmits<{ select: [] }>();
</script>

<template>
    <div class="w-40 shrink-0">
        <button
            type="button"
            :aria-pressed="selected"
            :aria-label="`${palette.label ?? 'パレット'} ${palette.colors.join(' ')}`"
            :class="[
                'block w-full overflow-hidden rounded-lg border-2 transition',
                selected
                    ? 'border-gray-900 shadow-md'
                    : 'border-transparent hover:border-gray-300',
            ]"
            @click="$emit('select')"
        >
            <span class="flex h-16">
                <span
                    v-for="(hex, index) in palette.colors"
                    :key="index"
                    data-swatch
                    class="flex-1"
                    :style="{ backgroundColor: hex }"
                />
            </span>
        </button>
        <p v-if="palette.label" class="mt-1 text-center text-xs text-gray-600">
            {{ palette.label }}
        </p>
    </div>
</template>
```

`resources/js/components/color/PaletteFamilyList.vue`:

```vue
<script setup lang="ts">
import type { Palette } from '@/lib/palette/types';

import PaletteCard from './PaletteCard.vue';

defineProps<{
    groups: { key: string; label: string; palettes: Palette[] }[];
    selectedId: string | null;
}>();
defineEmits<{ select: [id: string] }>();
</script>

<template>
    <div class="space-y-6">
        <section v-for="group in groups" :key="group.key">
            <h2 class="text-sm font-medium text-gray-700">{{ group.label }}</h2>
            <div class="mt-2 flex flex-wrap gap-4">
                <PaletteCard
                    v-for="palette in group.palettes"
                    :key="palette.id"
                    :palette="palette"
                    :selected="palette.id === selectedId"
                    @select="$emit('select', palette.id)"
                />
            </div>
        </section>
    </div>
</template>
```

`resources/js/components/color/MoodSelector.vue`:

```vue
<script setup lang="ts">
import { MOODS } from '@/lib/palette/moods';
import type { MoodId } from '@/lib/palette/types';

const props = defineProps<{
    mode: 'mood' | 'base';
    mood: MoodId;
    base: string;
}>();

const emit = defineEmits<{
    'select-mood': [id: MoodId];
    'select-base': [hex: string];
}>();

function onBaseInput(event: Event) {
    emit('select-base', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <div>
        <p class="text-sm font-medium text-gray-700">どんな雰囲気にしますか？</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="item in MOODS"
                :key="item.id"
                type="button"
                :data-mood="item.id"
                :aria-pressed="props.mode === 'mood' && props.mood === item.id"
                :title="item.hint"
                :class="[
                    'rounded-full border px-4 py-1.5 text-sm transition',
                    props.mode === 'mood' && props.mood === item.id
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-500',
                ]"
                @click="emit('select-mood', item.id)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button
                type="button"
                data-base-toggle
                :aria-pressed="props.mode === 'base'"
                :class="[
                    'rounded-full border px-4 py-1.5 text-sm transition',
                    props.mode === 'base'
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-500',
                ]"
                @click="emit('select-base', props.base)"
            >
                基準色から作る
            </button>
            <input
                v-if="props.mode === 'base'"
                type="color"
                :value="props.base"
                aria-label="基準色"
                class="h-9 w-16 cursor-pointer rounded-md border border-gray-200"
                @input="onBaseInput"
            />
            <span
                v-if="props.mode === 'base'"
                class="font-mono text-sm text-gray-600"
                >{{ props.base }}</span
            >
        </div>
    </div>
</template>
```

- [ ] **Step 4: 通ることを確認する**

Run: `npx vitest run resources/js/components/color`
Expected: PASS

- [ ] **Step 5: 型・Lint・コミット**

Run: `npm run types:check && npx eslint resources/js/components/color`

```bash
git add resources/js/components/color
git commit -m "feat(m8): 気分の選択・パレットカード・系統別の一覧

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 4: 確認・持ち帰る側の部品（詳細・サイトのひな形）

**Files:**
- Create: `resources/js/components/color/PaletteDetail.vue`、`SitePreview.vue`
- Test: `PaletteDetail.test.ts`、`SitePreview.test.ts`（同ディレクトリ）

**Interfaces:**
- Consumes: `Palette`、`PaletteColors`、`ROLE_KEYS`（types）、`ROLE_INFO`・`contrastChecks`・`toTheme`・`readableOn`・`isDarkColors`（roles）、`toCssVariables`・`toTailwind`・`toHexList`（export）
- Produces:
  - `PaletteDetail`：props `palette: Palette`（emits なし。コピーは内部で `navigator.clipboard`）
  - `SitePreview`：props `colors: PaletteColors`（ライト／ダークの切り替えは内部）

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/components/color/PaletteDetail.test.ts`:

```ts
// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

import type { Palette } from '@/lib/palette/types';

import PaletteDetail from './PaletteDetail.vue';

const palette: Palette = {
    id: 'blue-0',
    mood: 'gentle',
    colors: ['#f5f8ff', '#e6edfb', '#2f6f8f', '#e07a3f', '#1a2433'],
};

const writeText = vi.fn().mockResolvedValue(undefined);

beforeEach(() => {
    writeText.mockClear();
    Object.assign(navigator, { clipboard: { writeText } });
});

test('5 つの役割を、使いどころと割合の目安つきで出す', () => {
    const wrapper = mount(PaletteDetail, { props: { palette } });

    for (const label of ['ベース', 'サブ背景', 'メイン', 'アクセント', '文字']) {
        expect(wrapper.text()).toContain(label);
    }
    expect(wrapper.text()).toContain('60%');
    expect(wrapper.text()).toContain('25%');
    expect(wrapper.text()).toContain('10%');
    expect(wrapper.text()).toContain('5%');
});

test('コントラスト判定を AA/AAA つきで出す', () => {
    const wrapper = mount(PaletteDetail, { props: { palette } });
    expect(wrapper.text()).toContain('文字 × ベース');
    expect(wrapper.text()).toMatch(/AAA|AA/);
});

test('HEX を押すとコピーし、「コピーしました」を出す', async () => {
    const wrapper = mount(PaletteDetail, { props: { palette } });

    await wrapper.find('[data-hex="#2f6f8f"]').trigger('click');
    await flushPromises();

    expect(writeText).toHaveBeenCalledWith('#2f6f8f');
    expect(wrapper.text()).toContain('コピーしました');
});

test('CSS 変数・Tailwind・HEX 一覧のコピーボタンがある', async () => {
    const wrapper = mount(PaletteDetail, { props: { palette } });

    await wrapper.find('[data-export="css"]').trigger('click');
    await flushPromises();
    expect(writeText).toHaveBeenLastCalledWith(expect.stringContaining('--color-main: #2f6f8f;'));

    await wrapper.find('[data-export="tailwind"]').trigger('click');
    await flushPromises();
    expect(writeText).toHaveBeenLastCalledWith(expect.stringContaining("main: '#2f6f8f',"));

    await wrapper.find('[data-export="hex"]').trigger('click');
    await flushPromises();
    expect(writeText).toHaveBeenLastCalledWith(expect.stringContaining('メイン #2f6f8f'));
});

test('クリップボードが使えなくてもエラーにならない', async () => {
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) },
    });
    const wrapper = mount(PaletteDetail, { props: { palette } });

    await wrapper.find('[data-hex="#2f6f8f"]').trigger('click');
    await flushPromises();

    expect(wrapper.text()).not.toContain('コピーしました');
    expect(wrapper.text()).toContain('#2f6f8f');
});
```

`resources/js/components/color/SitePreview.test.ts`:

```ts
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import type { PaletteColors } from '@/lib/palette/types';

import SitePreview from './SitePreview.vue';

const LIGHT: PaletteColors = ['#f5f8ff', '#e6edfb', '#2f6f8f', '#e07a3f', '#1a2433'];
const DARK: PaletteColors = ['#10151f', '#1a2130', '#7aa8e0', '#f0a35c', '#e8edf5'];

test('ひな形にヘッダー・ヒーロー・カード・フォームが出る', () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });

    expect(wrapper.find('[data-preview="header"]').exists()).toBe(true);
    expect(wrapper.find('[data-preview="hero"]').exists()).toBe(true);
    expect(wrapper.findAll('[data-preview="card"]').length).toBeGreaterThanOrEqual(3);
    expect(wrapper.find('[data-preview="form"]').exists()).toBe(true);
});

test('ライトのパレットは最初ライト表示、ベース色が背景になる', () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });
    const root = wrapper.find('[data-preview="root"]');

    expect(root.attributes('style')).toContain('background-color: rgb(245, 248, 255)');
    expect(wrapper.find('[data-mode="light"]').attributes('aria-pressed')).toBe('true');
});

test('ダークのパレットは最初ダーク表示', () => {
    const wrapper = mount(SitePreview, { props: { colors: DARK } });
    expect(wrapper.find('[data-mode="dark"]').attributes('aria-pressed')).toBe('true');
});

test('切り替えボタンで逆の向きの背景に変わる', async () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });
    await wrapper.find('[data-mode="dark"]').trigger('click');

    const style = wrapper.find('[data-preview="root"]').attributes('style') ?? '';
    expect(style).not.toContain('rgb(245, 248, 255)');
    expect(wrapper.find('[data-mode="dark"]').attributes('aria-pressed')).toBe('true');
});

test('パレットが変わると、そのパレットの向きに戻る', async () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });
    await wrapper.find('[data-mode="dark"]').trigger('click');
    await wrapper.setProps({ colors: DARK });

    expect(wrapper.find('[data-mode="dark"]').attributes('aria-pressed')).toBe('true');
    await wrapper.setProps({ colors: LIGHT });
    expect(wrapper.find('[data-mode="light"]').attributes('aria-pressed')).toBe('true');
});
```

- [ ] **Step 2: 失敗を確認する**

Run: `npx vitest run resources/js/components/color/PaletteDetail.test.ts resources/js/components/color/SitePreview.test.ts`
Expected: FAIL

- [ ] **Step 3: 実装する**

`resources/js/components/color/PaletteDetail.vue`:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';

import { toCssVariables, toHexList, toTailwind } from '@/lib/palette/export';
import { ROLE_INFO, contrastChecks } from '@/lib/palette/roles';
import { ROLE_KEYS, type Palette } from '@/lib/palette/types';

const props = defineProps<{ palette: Palette }>();

const copied = ref('');
let resetTimer: ReturnType<typeof setTimeout> | null = null;

async function copy(key: string, text: string) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;

        if (resetTimer) {
            clearTimeout(resetTimer);
        }

        resetTimer = setTimeout(() => {
            copied.value = '';
        }, 1500);
    } catch {
        // クリップボードが使えなくても、値は画面に見えている。
    }
}

const roles = computed(() =>
    ROLE_KEYS.map((key, index) => ({
        key,
        hex: props.palette.colors[index],
        ...ROLE_INFO[key],
    })),
);

const checks = computed(() => contrastChecks(props.palette.colors));

const exports = computed(() => [
    { id: 'css', label: 'CSS変数をコピー', text: toCssVariables(props.palette.colors) },
    { id: 'tailwind', label: 'Tailwindをコピー', text: toTailwind(props.palette.colors) },
    { id: 'hex', label: 'HEX一覧をコピー', text: toHexList(props.palette.colors) },
]);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-sm font-medium text-gray-700">役割と割合の目安</h2>
            <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                <li
                    v-for="role in roles"
                    :key="role.key"
                    class="flex items-center gap-3 px-3 py-2"
                >
                    <span
                        class="h-8 w-8 shrink-0 rounded-md border border-gray-200"
                        :style="{ backgroundColor: role.hex }"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900">
                            {{ role.label }}
                            <span v-if="role.share !== null" class="ml-1 text-xs text-gray-500"
                                >{{ role.share }}%</span
                            >
                        </span>
                        <span class="block text-xs text-gray-500">{{ role.use }}</span>
                    </span>
                    <button
                        type="button"
                        :data-hex="role.hex"
                        class="font-mono text-sm text-gray-700 hover:text-gray-900"
                        :aria-label="`${role.hex} をコピー`"
                        @click="copy(role.hex, role.hex)"
                    >
                        {{ copied === role.hex ? 'コピーしました' : role.hex }}
                    </button>
                </li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-medium text-gray-700">読みやすさ（WCAG）</h2>
            <ul class="mt-2 space-y-1 text-sm">
                <li
                    v-for="check in checks"
                    :key="check.label"
                    class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-1.5"
                >
                    <span class="text-gray-700">{{ check.label }}</span>
                    <span class="font-mono text-gray-900"
                        >{{ check.ratio.toFixed(1) }}:1
                        <span
                            :class="[
                                'ml-2 rounded px-1.5 py-0.5 text-xs',
                                check.grade === '不適合'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-green-100 text-green-700',
                            ]"
                            >{{ check.grade }}</span
                        ></span
                    >
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="item in exports"
                :key="item.id"
                type="button"
                :data-export="item.id"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-gray-500"
                @click="copy(item.id, item.text)"
            >
                {{ copied === item.id ? 'コピーしました' : item.label }}
            </button>
        </div>
    </div>
</template>
```

`resources/js/components/color/SitePreview.vue`:

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { isDarkColors, readableOn, toTheme } from '@/lib/palette/roles';
import type { PaletteColors } from '@/lib/palette/types';

const props = defineProps<{ colors: PaletteColors }>();

const mode = ref<'light' | 'dark'>(isDarkColors(props.colors) ? 'dark' : 'light');

watch(
    () => props.colors,
    (colors) => {
        mode.value = isDarkColors(colors) ? 'dark' : 'light';
    },
);

const theme = computed(() => toTheme(props.colors, mode.value));
const onMain = computed(() => readableOn(theme.value.main));
const onAccent = computed(() => readableOn(theme.value.accent));
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-medium text-gray-700">Webページに当てはめる</h2>
            <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
                <button
                    type="button"
                    data-mode="light"
                    :aria-pressed="mode === 'light'"
                    :class="['px-3 py-1', mode === 'light' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700']"
                    @click="mode = 'light'"
                >
                    ライト
                </button>
                <button
                    type="button"
                    data-mode="dark"
                    :aria-pressed="mode === 'dark'"
                    :class="['px-3 py-1', mode === 'dark' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700']"
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
                <span class="font-bold" :style="{ color: theme.main }">Sample</span>
                <nav class="flex gap-4 text-sm">
                    <span>機能</span>
                    <span>料金</span>
                    <span :style="{ color: theme.accent }">お問い合わせ</span>
                </nav>
            </header>

            <section data-preview="hero" class="px-5 py-10 text-center">
                <h3 class="text-2xl font-bold" :style="{ color: theme.main }">
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
                        :style="{ backgroundColor: theme.accent, color: onAccent }"
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
                    <p class="text-sm font-bold" :style="{ color: theme.main }">
                        特長 {{ n }}
                    </p>
                    <p class="mt-1 text-xs">短い説明が入ります。文字の読みやすさを確認できます。</p>
                    <span
                        class="mt-3 inline-block rounded px-2 py-0.5 text-xs"
                        :style="{ backgroundColor: theme.accent, color: onAccent }"
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
                    :style="{ backgroundColor: theme.sub, border: `1px solid ${theme.main}` }"
                    >メールアドレス</span
                >
                <span
                    class="rounded-md px-4 py-2 text-sm font-medium"
                    :style="{ backgroundColor: theme.main, color: onMain }"
                    >登録する</span
                >
            </section>
        </div>
    </div>
</template>
```

- [ ] **Step 4: 通ることを確認する**

Run: `npx vitest run resources/js/components/color`
Expected: PASS（Task 3 の分も含めて全件）

- [ ] **Step 5: 型・Lint・コミット**

Run: `npm run types:check && npx eslint resources/js/components/color`

```bash
git add resources/js/components/color
git commit -m "feat(m8): パレット詳細（役割・コントラスト・コピー）とサイトのひな形

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 5: ページ・バックエンド・旧ツールの統合

**Files:**
- Create: `resources/js/pages/ColorGenerator.vue`、`resources/js/pages/ColorGenerator.test.ts`、`app/Http/Controllers/Tools/ColorGeneratorController.php`、`tests/Feature/Tools/ColorGeneratorControllerTest.php`
- Modify: `routes/web.php`、`config/tools.php`
- Delete: `resources/js/pages/ColorScheme.vue`、`ColorPalette.vue`、`app/Http/Controllers/Tools/ColorSchemeController.php`、`ColorPaletteController.php`、`tests/Feature/Tools/ColorSchemeControllerTest.php`、`ColorPaletteControllerTest.php`

**Interfaces:**
- Consumes: Task 1〜4 のすべて。`PageHeader`（`title`・`description`）、`PageMain`、`PublicLayout`
- Produces: `GET /color-generator`（Inertia `ColorGenerator`）、`POST /color-generator/track`、`route('color-generator.show')`・`route('color-generator.track')`

- [ ] **Step 1: バックエンドの失敗するテストを書く**

`tests/Feature/Tools/ColorGeneratorControllerTest.php`:

```php
<?php

use App\Models\ToolEvent;

test('the color-generator page renders', function () {
    $response = $this->get(route('color-generator.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorGenerator'));
});

test('posting to the track endpoint logs a process event', function () {
    $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-generator.track'))
        ->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-generator')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the old color-scheme and color-palette pages redirect permanently to the generator', function (string $path) {
    $this->get($path)->assertStatus(301)->assertRedirect('/color-generator');
})->with(['/color-scheme', '/color-palette']);

test('the tool list has the generator and no longer lists the merged tools', function () {
    $slugs = collect(config('tools'))->pluck('slug');

    expect($slugs)->toContain('color-generator')
        ->and($slugs)->not->toContain('color-scheme')
        ->and($slugs)->not->toContain('color-palette');
});
```

- [ ] **Step 2: 失敗を確認する**

Run: `php artisan test --filter=ColorGeneratorControllerTest`
Expected: FAIL（route 未定義）

- [ ] **Step 3: バックエンドを実装する**

`app/Http/Controllers/Tools/ColorGeneratorController.php`:

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class ColorGeneratorController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'color-generator';
    }

    protected function page(): string
    {
        return 'ColorGenerator';
    }
}
```

`routes/web.php`：先頭の `use` 群で `ColorPaletteController`・`ColorSchemeController` の import を、`ColorGeneratorController` の import に差し替える。次に、`color-palette` の 3 本（show・track・examples.copy）と `color-scheme` の 3 本を削除し、`color-contrast` の後ろ（`color-palette` があった位置）に次を置く：

```php
Route::get('/color-generator', [ColorGeneratorController::class, 'show'])->name('color-generator.show');
Route::post('/color-generator/track', [ColorGeneratorController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-generator.track');

// M8: 旧「カラーパレット生成」「色の組み合わせ」は配色ジェネレーターに統合した。
Route::redirect('/color-palette', '/color-generator', 301);
Route::redirect('/color-scheme', '/color-generator', 301);
```

`config/tools.php`：`color-palette` のエントリと `color-scheme` のエントリを削除し、`color-contrast` の後ろに次を追加する：

```php
    [
        'slug' => 'color-generator',
        'name' => '配色ジェネレーター',
        'category' => 'color',
        'description' => '気分を選ぶとパレットが並び、ベース・メイン・アクセントの役割とコントラストを確認して、Webページに当てはめ、CSS変数でコピーできます。',
        'href' => '/color-generator',
    ],
```

旧ファイルを削除する：

```bash
git rm resources/js/pages/ColorScheme.vue resources/js/pages/ColorPalette.vue \
  app/Http/Controllers/Tools/ColorSchemeController.php app/Http/Controllers/Tools/ColorPaletteController.php \
  tests/Feature/Tools/ColorSchemeControllerTest.php tests/Feature/Tools/ColorPaletteControllerTest.php
```

- [ ] **Step 4: ページの失敗するテストを書く**

`resources/js/pages/ColorGenerator.test.ts`（既存のページテスト `ImageCompress.test.ts` と同じ要領で、`@inertiajs/vue3` とレイアウトをモックする。書き始める前に `ImageCompress.test.ts` の冒頭のモック部分を読んで、同じ形に合わせること）:

```ts
// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, expect, test, vi } from 'vitest';

import ColorGenerator from './ColorGenerator.vue';

vi.mock('axios');
vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
}));

const stubs = { PublicLayout: { template: '<div><slot /></div>' } };

function setUrl(search: string) {
    window.history.replaceState(null, '', `/color-generator${search}`);
}

beforeEach(() => {
    setUrl('');
    vi.mocked(axios.post).mockResolvedValue({ data: { status: 'ok' } });
    Object.assign(navigator, { clipboard: { writeText: vi.fn().mockResolvedValue(undefined) } });
});

test('既定はやさしい気分で、系統ごとのパレットと先頭の詳細・プレビューが出る', () => {
    const wrapper = mount(ColorGenerator, { global: { stubs } });

    expect(wrapper.find('[data-mood="gentle"]').attributes('aria-pressed')).toBe('true');
    expect(wrapper.findAll('button[aria-pressed]').length).toBeGreaterThan(10);
    expect(wrapper.text()).toContain('役割と割合の目安');
    expect(wrapper.find('[data-preview="root"]').exists()).toBe(true);
});

test('URL の気分とパレットを復元する', () => {
    setUrl('?mood=trust&p=blue-1');
    const wrapper = mount(ColorGenerator, { global: { stubs } });

    expect(wrapper.find('[data-mood="trust"]').attributes('aria-pressed')).toBe('true');
    expect(wrapper.text()).not.toContain('赤系');
});

test('不正な URL は既定に戻り、存在しないパレットは先頭を使う', () => {
    setUrl('?mood=zzz&p=nope-9');
    const wrapper = mount(ColorGenerator, { global: { stubs } });

    expect(wrapper.find('[data-mood="gentle"]').attributes('aria-pressed')).toBe('true');
    expect(wrapper.find('[data-preview="root"]').exists()).toBe(true);
});

test('気分を押すと URL を書き換え、計測を 1 回送る', async () => {
    vi.useFakeTimers();
    const wrapper = mount(ColorGenerator, { global: { stubs } });

    await wrapper.find('[data-mood="warm"]').trigger('click');
    expect(window.location.search).toContain('mood=warm');

    vi.advanceTimersByTime(600);
    await flushPromises();
    expect(axios.post).toHaveBeenCalledWith('/color-generator/track');
    expect(vi.mocked(axios.post).mock.calls).toHaveLength(1);
    vi.useRealTimers();
});

test('パレットを押すと p が URL に入る', async () => {
    const wrapper = mount(ColorGenerator, { global: { stubs } });

    await wrapper.findAll('button[aria-pressed]').find((b) => b.attributes('aria-label')?.startsWith('パレット'))!.trigger('click');
    expect(window.location.search).toMatch(/p=[a-z]+-\d/);
});

test('基準色モードでは 5 つのパターンが並ぶ', () => {
    setUrl('?base=2f6f8f');
    const wrapper = mount(ColorGenerator, { global: { stubs } });

    for (const label of ['類似色', '補色', '分割補色', 'トライアド', '同系色']) {
        expect(wrapper.text()).toContain(label);
    }
});
```

- [ ] **Step 5: 失敗を確認する**

Run: `npx vitest run resources/js/pages/ColorGenerator.test.ts`
Expected: FAIL（ページが無い）

- [ ] **Step 6: ページを実装する**

`resources/js/pages/ColorGenerator.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, watch } from 'vue';

import MoodSelector from '@/components/color/MoodSelector.vue';
import PaletteDetail from '@/components/color/PaletteDetail.vue';
import PaletteFamilyList from '@/components/color/PaletteFamilyList.vue';
import SitePreview from '@/components/color/SitePreview.vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { fromBase, generateAll } from '@/lib/palette/generate';
import {
    parseState,
    serializeState,
    type GeneratorState,
} from '@/lib/palette/urlState';
import { BASE_PATTERNS, type MoodId, type Palette } from '@/lib/palette/types';

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

                <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                    <PaletteFamilyList
                        :groups="groups"
                        :selected-id="selected?.id ?? null"
                        @select="state.selected = $event"
                    />

                    <div v-if="selected" class="space-y-8">
                        <PaletteDetail :palette="selected" />
                        <SitePreview :colors="selected.colors" />
                    </div>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
```

テスト内の「パレットを押す」は `aria-label` が `パレット ` で始まるカードを探す。`PaletteCard` の `aria-label` は `${palette.label ?? 'パレット'} ${colors}` なので一致する。

- [ ] **Step 7: テストを通す**

Run: `npx vitest run resources/js/pages/ColorGenerator.test.ts && php artisan wayfinder:generate && php artisan test --filter=ColorGeneratorControllerTest`
Expected: すべて PASS

- [ ] **Step 8: 全体の確認**

Run: `composer test && npx vitest run && npm run lint && npm run types:check`
Expected: すべて通る。落ちるのは、削除したテスト（旧 2 コントローラ）に依存した箇所や、ツール数・件数を数えるテスト（`HomeControllerTest` など）のはず。ツール総数やカテゴリ件数を固定値で検査しているテストは、色カテゴリが 5 → 4 件になった事実に合わせて期待値を直す（旧ツールのスラッグを使うフィクスチャ `Home.test.ts`・`toolFilter.test.ts` は架空データなので触らない）。

- [ ] **Step 9: コミット**

```bash
git add -A
git commit -m "feat(m8): 配色ジェネレーターの画面・route・旧ツールの統合

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 6: 仕様書・記録・ブラウザ確認

**Files:**
- Modify: `SPEC.md`（§17.3・§17.5 の旧ツール記述に統合済みの注記、新ツールの節を追加）、`TASKS.md`（M8 の完了を追記）、`docs/superpowers/specs/2026-10-12-m8-color-generator-design.md`（ステータスと URL の形式を実装に合わせる）
- Modify（会社側）: `/Users/katsuhiro.k1215/SmartSprouts/company/rooms/katsuooool/dev/roadmap/aws-migration-and-ui.md`（M8 の結果メモ、状態を更新）

- [ ] **Step 1: SPEC.md を、範囲を絞って更新する（他の節を消さない）**

`Edit` ツールで、次の 2 点だけを行う。一括置換（`sed`・正規表現）は使わない（M5-3 で関係ない節を消した事故があった）。
1. §17.3 の見出し直後と §17.5 の見出し直後に、「> M8（2026-10-12）で『配色ジェネレーター』（§17.9）に統合した。旧 URL は 301 転送。」の 1 行を追加する。
2. §17 の末尾（次の大見出しの直前）に「### 17.9 配色ジェネレーター(`/color-generator`)」の節を追加し、設計書（目的、気分 10 個、生成の方式、URL 形式、役割と割合、コピー形式、統合の扱い）を要約して書く。節番号が既に使われていたら、空いている次の番号にする。

- [ ] **Step 2: 設計書を実装に合わせる**

設計書の §4.2 の URL 形式を `?mood=<id>&p=<系統>-<番号>`／`?base=<6桁hex>&p=<パターンid>` に直し、ステータスを「実装済み（`docs/superpowers/plans/2026-10-12-m8-color-generator.md`）。本番反映は未実施。」にする。

- [ ] **Step 3: ブラウザで確認する**

開発サーバー（`composer dev`）を起動し、Playwright（playwright-core とキャッシュ済み Chromium。`executablePath` 指定）で、1280px と 390px の両方を撮って見る。確認項目：
- 既定（やさしい）で、色相の行とカードが並び、先頭の詳細とプレビューが出る。
- 10 個の気分を順に押し、見た目が気分に合っている（特にダーク、モダン、ポップ）。違和感があれば `moods.ts` の値を調整する（調整後は Task 1 のテストを再実行）。
- カードを押すと詳細とプレビューが変わり、URL に `p=` が入る。URL を直接開くと同じ状態で復元される。
- ライト／ダーク切り替え、HEX・CSS 変数・Tailwind・HEX 一覧のコピー。
- 基準色モード（色を変えると 5 パターンが並ぶ）。
- 横スクロールがない、コンソールエラーがない、`/color-scheme`・`/color-palette` が `/color-generator` に転送される。
- サイドバーの「カラー」の件数が 4 件になっている。

- [ ] **Step 4: 全体の確認とコミット**

Run: `composer test && npx vitest run && npm run lint && npm run types:check`
Expected: すべて通る

```bash
git add -A
git commit -m "docs(m8): 仕様書・設計書を実装に合わせる

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 5: 会社側のロードマップに結果メモを追記する**

`aws-migration-and-ui.md` の M8 行の状態を「実装済み（本番反映待ち）」にし、「M8 の結果メモ」節を追加する（ブランチ名、設計書・計画のパス、統合した内容、検証結果、本番反映は `app`・`scheduler`・`katsuooool-web` の 3 つ）。

---

## 計画の自己確認

- 設計書の対応：§1 統合・転送・ツール一覧＝Task 5／§4.1 生成ロジック＝Task 1・2／§4.2 画面・URL・計測＝Task 3・4・5／§4.3 バックエンド＝Task 5／§4.4 境界＝Task 1（読みやすさ・極端な基準色）、Task 2（不正な URL）、Task 4（クリップボード）、Task 5（存在しない p）／§5 テスト＝各タスク＋Task 6 のブラウザ確認／§6 やらないこと＝実装なし／§7 本番反映＝本番反映の段で実施。
- 設計書からの調整（実装に合わせて設計書も直す）：URL の `hue` を `p=<系統>-<番号>` に統合。旧 POST route は削除（404）。
- 型の一貫：`PaletteColors` の順序（base・sub・main・accent・text）、`Palette.id`（`<系統>-<番号>`／パターン id）、`GeneratorState.selected` は全タスクで同じ。
