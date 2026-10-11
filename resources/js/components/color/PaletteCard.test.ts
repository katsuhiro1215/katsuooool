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
