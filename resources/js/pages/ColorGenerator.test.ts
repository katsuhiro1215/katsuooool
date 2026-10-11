// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

const post = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({
    default: { post, isAxiosError: () => false },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        url: '/color-generator',
        props: { auth: { user: null }, toolNav: [] },
    }),
    router: { get: () => {} },
}));

import ColorGenerator from './ColorGenerator.vue';

function setUrl(search: string) {
    window.history.replaceState(null, '', `/color-generator${search}`);
}

beforeEach(() => {
    setUrl('');
    post.mockReset();
    vi.stubGlobal('route', (name: string) => `/${name}`);
    post.mockResolvedValue({ data: { status: 'ok' } });
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
});

test('既定はやさしい気分で、系統ごとのパレットと先頭の詳細・プレビューが出る', () => {
    const wrapper = mount(ColorGenerator);

    expect(
        wrapper.find('[data-mood="gentle"]').attributes('aria-pressed'),
    ).toBe('true');
    expect(wrapper.text()).toContain('赤系');
    expect(wrapper.text()).toContain('役割と割合の目安');
    expect(wrapper.find('[data-preview="root"]').exists()).toBe(true);
});

test('URL の気分とパレットを復元する', () => {
    setUrl('?mood=trust&p=blue-1');
    const wrapper = mount(ColorGenerator);

    expect(wrapper.find('[data-mood="trust"]').attributes('aria-pressed')).toBe(
        'true',
    );
    expect(wrapper.text()).toContain('青系');
    expect(wrapper.text()).not.toContain('赤系');
});

test('不正な URL は既定に戻り、存在しないパレットは先頭を使う', () => {
    setUrl('?mood=zzz&p=nope-9');
    const wrapper = mount(ColorGenerator);

    expect(
        wrapper.find('[data-mood="gentle"]').attributes('aria-pressed'),
    ).toBe('true');
    expect(wrapper.find('[data-preview="root"]').exists()).toBe(true);
});

test('気分を押すと URL を書き換え、計測を 1 回送る', async () => {
    vi.useFakeTimers();
    const wrapper = mount(ColorGenerator);

    await wrapper.find('[data-mood="warm"]').trigger('click');
    expect(window.location.search).toContain('mood=warm');

    vi.advanceTimersByTime(600);
    await flushPromises();
    expect(post).toHaveBeenCalledTimes(1);
    expect(post).toHaveBeenCalledWith('/color-generator/track');
    vi.useRealTimers();
});

test('パレットを押すと p が URL に入る', async () => {
    const wrapper = mount(ColorGenerator);

    await wrapper.findAll('button[aria-label^="パレット"]')[1].trigger('click');
    expect(window.location.search).toMatch(/p=[a-z]+-\d/);
});

test('基準色モードでは 5 つのパターンが並ぶ', () => {
    setUrl('?base=2f6f8f');
    const wrapper = mount(ColorGenerator);

    for (const label of [
        '類似色',
        '補色',
        '分割補色',
        'トライアド',
        '同系色',
    ]) {
        expect(wrapper.text()).toContain(label);
    }
});

test('画面が狭いとき、パレットを選ぶと詳細までスクロールする', async () => {
    const scrollIntoView = vi.fn();
    Object.assign(Element.prototype, { scrollIntoView });
    vi.stubGlobal('innerWidth', 390);
    const wrapper = mount(ColorGenerator);

    await wrapper.findAll('button[aria-label^="パレット"]')[1].trigger('click');
    expect(scrollIntoView).toHaveBeenCalled();
});

test('画面が広いときはスクロールしない', async () => {
    const scrollIntoView = vi.fn();
    Object.assign(Element.prototype, { scrollIntoView });
    vi.stubGlobal('innerWidth', 1440);
    const wrapper = mount(ColorGenerator);

    await wrapper.findAll('button[aria-label^="パレット"]')[1].trigger('click');
    expect(scrollIntoView).not.toHaveBeenCalled();
});
