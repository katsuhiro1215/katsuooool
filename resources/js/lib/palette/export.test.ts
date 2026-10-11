import { expect, test } from 'vitest';

import { toCssVariables, toHexList, toTailwind } from './export';
import type { PaletteColors } from './types';

const COLORS: PaletteColors = [
    '#f5f8ff',
    '#e6edfb',
    '#2f6f8f',
    '#e07a3f',
    '#1a2433',
];

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
