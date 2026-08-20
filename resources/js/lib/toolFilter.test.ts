import { describe, expect, test } from 'vitest';

import { CATEGORY_TABS, filterTools } from './toolFilter';
import type { Tool } from './toolFilter';

const tools: Tool[] = [
    {
        slug: 'image-compress',
        name: '画像圧縮',
        category: 'image',
        description: 'JPEG・PNG・WebP画像を自動で最適化します。',
        href: '/image-compress',
    },
    {
        slug: 'color-palette',
        name: 'カラーパレット生成',
        category: 'color',
        description: 'ランダムな5色のカラーパレットを生成します。',
        href: '/color-palette',
    },
    {
        slug: 'css-box-shadow',
        name: 'Box Shadow ジェネレーター',
        category: 'css',
        description: 'box-shadowのCSSコードを生成します。',
        href: '/css-box-shadow',
    },
];

describe('filterTools', () => {
    test('returns all tools when category is "all" and query is empty', () => {
        expect(filterTools(tools, 'all', '')).toEqual(tools);
    });

    test('filters by category only', () => {
        const result = filterTools(tools, 'color', '');

        expect(result).toHaveLength(1);
        expect(result[0].slug).toBe('color-palette');
    });

    test('filters by keyword only, matching name or description, case-insensitively', () => {
        const result = filterTools(tools, 'all', 'box');

        expect(result).toHaveLength(1);
        expect(result[0].slug).toBe('css-box-shadow');
    });

    test('combines category and keyword with AND', () => {
        const result = filterTools(tools, 'image', '圧縮');

        expect(result).toHaveLength(1);
        expect(result[0].slug).toBe('image-compress');
    });

    test('returns an empty array when category matches but keyword does not', () => {
        expect(filterTools(tools, 'image', 'gradient')).toEqual([]);
    });

    test('returns an empty array when keyword matches but category does not', () => {
        expect(filterTools(tools, 'seo', '画像')).toEqual([]);
    });
});

describe('CATEGORY_TABS', () => {
    test('starts with an "all" tab labeled すべて', () => {
        expect(CATEGORY_TABS[0]).toEqual({ key: 'all', label: 'すべて' });
    });

    test('contains one tab per known tool category', () => {
        const keys = CATEGORY_TABS.map((tab) => tab.key);

        expect(keys).toEqual([
            'all',
            'image',
            'color',
            'css',
            'pattern',
            'generator',
            'developer',
            'seo',
        ]);
    });
});
