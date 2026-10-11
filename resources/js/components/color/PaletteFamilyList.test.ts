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
    const wrapper = mount(PaletteFamilyList, {
        props: { groups, selectedId: null },
    });

    expect(wrapper.text()).toContain('赤系');
    expect(wrapper.text()).toContain('青系');
    expect(wrapper.findAll('button[aria-pressed]')).toHaveLength(3);
});

test('選択中のカードだけ aria-pressed が true', () => {
    const wrapper = mount(PaletteFamilyList, {
        props: { groups, selectedId: 'blue-0' },
    });
    expect(wrapper.findAll('button[aria-pressed="true"]')).toHaveLength(1);
});

test('カードを押すとそのパレット id で select を出す', async () => {
    const wrapper = mount(PaletteFamilyList, {
        props: { groups, selectedId: null },
    });
    await wrapper.findAll('button[aria-pressed]')[1].trigger('click');
    expect(wrapper.emitted('select')?.[0]).toEqual(['red-1']);
});
