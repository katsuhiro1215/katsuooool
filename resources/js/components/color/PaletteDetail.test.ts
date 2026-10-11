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

    for (const label of [
        'ベース',
        'サブ背景',
        'メイン',
        'アクセント',
        '文字',
    ]) {
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
    expect(writeText).toHaveBeenLastCalledWith(
        expect.stringContaining('--color-main: #2f6f8f;'),
    );

    await wrapper.find('[data-export="tailwind"]').trigger('click');
    await flushPromises();
    expect(writeText).toHaveBeenLastCalledWith(
        expect.stringContaining("main: '#2f6f8f',"),
    );

    await wrapper.find('[data-export="hex"]').trigger('click');
    await flushPromises();
    expect(writeText).toHaveBeenLastCalledWith(
        expect.stringContaining('メイン #2f6f8f'),
    );
});

test('クリップボードが使えなくてもエラーにならない', async () => {
    Object.assign(navigator, {
        clipboard: {
            writeText: vi.fn().mockRejectedValue(new Error('denied')),
        },
    });
    const wrapper = mount(PaletteDetail, { props: { palette } });

    await wrapper.find('[data-hex="#2f6f8f"]').trigger('click');
    await flushPromises();

    expect(wrapper.text()).not.toContain('コピーしました');
    expect(wrapper.text()).toContain('#2f6f8f');
});
