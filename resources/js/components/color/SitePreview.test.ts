// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import { contrastRatio, parseHex } from '@/lib/color';
import type { PaletteColors } from '@/lib/palette/types';

import SitePreview from './SitePreview.vue';

const LIGHT: PaletteColors = [
    '#f5f8ff',
    '#e6edfb',
    '#2f6f8f',
    '#e07a3f',
    '#1a2433',
];
const DARK: PaletteColors = [
    '#10151f',
    '#1a2130',
    '#7aa8e0',
    '#f0a35c',
    '#e8edf5',
];

test('ひな形にヘッダー・ヒーロー・カード・フォームが出る', () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });

    expect(wrapper.find('[data-preview="header"]').exists()).toBe(true);
    expect(wrapper.find('[data-preview="hero"]').exists()).toBe(true);
    expect(
        wrapper.findAll('[data-preview="card"]').length,
    ).toBeGreaterThanOrEqual(3);
    expect(wrapper.find('[data-preview="form"]').exists()).toBe(true);
});

test('ライトのパレットは最初ライト表示、ベース色が背景になる', () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });
    const root = wrapper.find('[data-preview="root"]');

    expect(root.attributes('style')).toContain(
        'background-color: rgb(245, 248, 255)',
    );
    expect(wrapper.find('[data-mode="light"]').attributes('aria-pressed')).toBe(
        'true',
    );
});

test('ダークのパレットは最初ダーク表示', () => {
    const wrapper = mount(SitePreview, { props: { colors: DARK } });
    expect(wrapper.find('[data-mode="dark"]').attributes('aria-pressed')).toBe(
        'true',
    );
});

test('切り替えボタンで逆の向きの背景に変わる', async () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });
    await wrapper.find('[data-mode="dark"]').trigger('click');

    const style =
        wrapper.find('[data-preview="root"]').attributes('style') ?? '';
    expect(style).not.toContain('rgb(245, 248, 255)');
    expect(wrapper.find('[data-mode="dark"]').attributes('aria-pressed')).toBe(
        'true',
    );
});

test('パレットが変わると、そのパレットの向きに戻る', async () => {
    const wrapper = mount(SitePreview, { props: { colors: LIGHT } });
    await wrapper.find('[data-mode="dark"]').trigger('click');
    await wrapper.setProps({ colors: DARK });

    expect(wrapper.find('[data-mode="dark"]').attributes('aria-pressed')).toBe(
        'true',
    );
    await wrapper.setProps({ colors: LIGHT });
    expect(wrapper.find('[data-mode="light"]').attributes('aria-pressed')).toBe(
        'true',
    );
});

test('淡いメイン色でも、見出しとリンクの文字色は背景に対して読める', () => {
    const PASTEL: PaletteColors = [
        '#f9f1f0',
        '#f1dbda',
        '#d89a97',
        '#85d3d6',
        '#2a1d1d',
    ];
    const wrapper = mount(SitePreview, { props: { colors: PASTEL } });
    const toRgb = (css: string) => {
        const m = /rgb\((\d+), (\d+), (\d+)\)/.exec(css)!;

        return { r: +m[1], g: +m[2], b: +m[3] };
    };
    const colorOf = (selector: string) =>
        toRgb((wrapper.find(selector).element as HTMLElement).style.color);
    const bg = parseHex('#f9f1f0')!;
    const sub = parseHex('#f1dbda')!;

    expect(
        contrastRatio(colorOf('[data-preview="heading"]'), bg),
    ).toBeGreaterThanOrEqual(3);
    expect(
        contrastRatio(colorOf('[data-preview="link"]'), sub),
    ).toBeGreaterThanOrEqual(4.5);
    expect(
        contrastRatio(colorOf('[data-preview="card-title"]'), sub),
    ).toBeGreaterThanOrEqual(3);
});
