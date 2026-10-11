export type HueFamilyId =
    'red' | 'orange' | 'yellow' | 'green' | 'teal' | 'blue' | 'purple' | 'pink';

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
    'analogous' | 'complement' | 'split' | 'triad' | 'mono';

export const BASE_PATTERNS: { id: BasePatternId; label: string }[] = [
    { id: 'analogous', label: '類似色' },
    { id: 'complement', label: '補色' },
    { id: 'split', label: '分割補色' },
    { id: 'triad', label: 'トライアド' },
    { id: 'mono', label: '同系色' },
];
