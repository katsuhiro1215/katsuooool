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
