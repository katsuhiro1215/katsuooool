// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import MoodSelector from './MoodSelector.vue';

test('気分ボタンを 10 個出し、押すと select-mood を出す', async () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'mood', mood: 'gentle', base: '#2f6f8f' },
    });

    expect(wrapper.findAll('[data-mood]')).toHaveLength(10);

    await wrapper.find('[data-mood="trust"]').trigger('click');
    expect(wrapper.emitted('select-mood')?.[0]).toEqual(['trust']);
});

test('気分モードでは現在の気分だけ aria-pressed が true', () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'mood', mood: 'warm', base: '#2f6f8f' },
    });

    expect(wrapper.find('[data-mood="warm"]').attributes('aria-pressed')).toBe(
        'true',
    );
    expect(
        wrapper.find('[data-mood="gentle"]').attributes('aria-pressed'),
    ).toBe('false');
    expect(wrapper.find('[data-base-toggle]').attributes('aria-pressed')).toBe(
        'false',
    );
});

test('基準色モードでは気分が押された状態にならず、基準色の入力が出る', () => {
    const wrapper = mount(MoodSelector, {
        props: { mode: 'base', mood: 'warm', base: '#2f6f8f' },
    });

    expect(wrapper.find('[data-mood="warm"]').attributes('aria-pressed')).toBe(
        'false',
    );
    expect(wrapper.find('[data-base-toggle]').attributes('aria-pressed')).toBe(
        'true',
    );
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
