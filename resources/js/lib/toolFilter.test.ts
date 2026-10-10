import { describe, expect, test } from 'vitest';

import {
    CATEGORY_TABS,
    countByCategory,
    extractQuery,
    filterTools,
    normalizeCategory,
    resolveActiveCategory,
} from './toolFilter';
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
            'ui-pattern',
        ]);
    });
});

describe('normalizeCategory', () => {
    test('returns known category keys as they are', () => {
        expect(normalizeCategory('image')).toBe('image');
    });

    test('falls back to all for unknown, empty or missing values', () => {
        expect(normalizeCategory('nope')).toBe('all');
        expect(normalizeCategory('')).toBe('all');
        expect(normalizeCategory(null)).toBe('all');
        expect(normalizeCategory(undefined)).toBe('all');
    });
});

describe('countByCategory', () => {
    test('counts tools per category and in total', () => {
        expect(countByCategory(tools)).toEqual({
            all: 3,
            image: 1,
            color: 1,
            css: 1,
        });
    });
});

describe('resolveActiveCategory', () => {
    test('uses the category query on the home page', () => {
        expect(resolveActiveCategory('/?category=color', tools)).toBe('color');
        expect(resolveActiveCategory('/', tools)).toBe('all');
    });

    test('treats an unknown category query as all', () => {
        expect(resolveActiveCategory('/?category=xxx', tools)).toBe('all');
    });

    test('uses the category of the tool whose href matches the path', () => {
        expect(resolveActiveCategory('/image-compress', tools)).toBe('image');
        expect(resolveActiveCategory('/css-box-shadow?x=1', tools)).toBe('css');
    });

    test('returns null for pages that are not tools', () => {
        expect(resolveActiveCategory('/contact', tools)).toBeNull();
    });
});

describe('extractQuery', () => {
    test('returns the decoded q parameter', () => {
        expect(extractQuery('/?q=%E7%94%BB%E5%83%8F%26a')).toBe('画像&a');
    });

    test('returns an empty string when q is absent', () => {
        expect(extractQuery('/?category=image')).toBe('');
        expect(extractQuery('/')).toBe('');
    });
});

describe('URL のフラグメント・末尾スラッシュ・空白', () => {
    test('ignores a #fragment when resolving the category query', () => {
        expect(resolveActiveCategory('/?category=image#top', tools)).toBe(
            'image',
        );
        expect(resolveActiveCategory('/#foo', tools)).toBe('all');
    });

    test('ignores a #fragment when matching a tool path', () => {
        expect(resolveActiveCategory('/image-compress#usage', tools)).toBe(
            'image',
        );
    });

    test('ignores a trailing slash when matching a tool path', () => {
        expect(resolveActiveCategory('/image-compress/', tools)).toBe('image');
    });

    test('ignores a #fragment in the search query', () => {
        expect(extractQuery('/?q=%E8%89%B2#x')).toBe('色');
    });

    test('treats a whitespace-only query as empty', () => {
        expect(extractQuery('/?q=%20%20')).toBe('');
    });
});
