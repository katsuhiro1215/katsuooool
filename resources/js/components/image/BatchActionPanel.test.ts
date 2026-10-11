// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import BatchActionPanel from './BatchActionPanel.vue';

const base = {
    phase: 'ready' as const,
    canSubmit: true,
    submitLabel: '圧縮する',
    processingMessage: '処理しています',
    summary: null,
    zipUrl: null,
    countLabel: '3 / 10枚',
};

test('ready: shows the count, the options slot and an enabled submit button', async () => {
    const wrapper = mount(BatchActionPanel, {
        props: base,
        slots: { default: '<p>オプション</p>' },
    });

    expect(wrapper.text()).toContain('3 / 10枚');
    expect(wrapper.text()).toContain('オプション');

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('submit')).toHaveLength(1);
});

test('the submit button is disabled when canSubmit is false', () => {
    const wrapper = mount(BatchActionPanel, {
        props: { ...base, canSubmit: false },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
});

test('processing: shows the message and disables the button', () => {
    const wrapper = mount(BatchActionPanel, {
        props: { ...base, phase: 'processing' },
    });

    expect(wrapper.text()).toContain('処理しています');
    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
});

test('done: shows the totals, the ZIP link, redo and clear', async () => {
    const wrapper = mount(BatchActionPanel, {
        props: {
            ...base,
            phase: 'done',
            summary: {
                count: 2,
                inputBytes: 4 * 1024 * 1024,
                outputBytes: 1024 * 1024,
                percent: 75,
            },
            zipUrl: '/zip',
        },
    });

    expect(wrapper.text()).toContain('4.0 MB');
    expect(wrapper.text()).toContain('1.0 MB');
    expect(wrapper.text()).toContain('75%');
    expect(wrapper.find('a[href="/zip"]').exists()).toBe(true);

    const buttons = wrapper.findAll('button');
    await buttons.find((b) => b.text().includes('やり直'))!.trigger('click');
    await buttons
        .find((b) => b.text().includes('すべて削除'))!
        .trigger('click');

    expect(wrapper.emitted('redo')).toHaveLength(1);
    expect(wrapper.emitted('clear')).toHaveLength(1);
});

test('done without a ZIP (single file) shows no ZIP link', () => {
    const wrapper = mount(BatchActionPanel, {
        props: {
            ...base,
            phase: 'done',
            summary: { count: 1, inputBytes: 10, outputBytes: 5, percent: 50 },
        },
    });

    expect(wrapper.find('a').exists()).toBe(false);
});
