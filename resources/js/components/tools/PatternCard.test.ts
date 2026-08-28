// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, expect, test, vi } from 'vitest';

import PatternCard from './PatternCard.vue';

vi.mock('axios');

const html = '<a href="#">Button</a>';
const css = 'a { color: red; }';

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', (_name: string, params: number) => {
        return `/ui-pattern-snippets/${params}/copy`;
    });
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
    vi.mocked(axios.post).mockResolvedValue({ data: { status: 'ok' } });
});

test('switches the displayed code from HTML to CSS when the CSS tab is clicked', async () => {
    const wrapper = mount(PatternCard, {
        props: { id: 1, title: 'サンプルボタン', locked: false, html, css },
    });

    expect(wrapper.find('pre').text()).toBe(html);

    const cssTab = wrapper
        .findAll('button')
        .find((button) => button.text() === 'CSS');
    await cssTab?.trigger('click');

    expect(wrapper.find('pre').text()).toBe(css);
});

test('embeds the css content in the preview iframe srcdoc', () => {
    const wrapper = mount(PatternCard, {
        props: { id: 1, title: 'サンプルボタン', locked: false, html, css },
    });

    const srcdoc = wrapper.find('iframe').attributes('srcdoc');
    expect(srcdoc).toContain(css);
});

test('a locked pattern shows the server-provided srcdoc without any code tabs or copy button', () => {
    const wrapper = mount(PatternCard, {
        props: {
            id: 1,
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc:
                '<!doctype html><html><body>locked preview</body></html>',
        },
    });

    expect(wrapper.find('iframe').attributes('srcdoc')).toBe(
        '<!doctype html><html><body>locked preview</body></html>',
    );
    expect(wrapper.find('pre').exists()).toBe(false);
    expect(
        wrapper.findAll('button').find((button) => button.text() === 'コピー'),
    ).toBeUndefined();
});

test('a locked pattern shows an upsell link to /premium', () => {
    const wrapper = mount(PatternCard, {
        props: {
            id: 1,
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc:
                '<!doctype html><html><body>locked preview</body></html>',
        },
    });

    const upsellLink = wrapper.find('a[href="/premium"]');
    expect(upsellLink.exists()).toBe(true);
});

test('clicking copy posts to the copy-tracking endpoint with the pattern id', async () => {
    const wrapper = mount(PatternCard, {
        props: { id: 42, title: 'サンプルボタン', locked: false, html, css },
    });

    const copyButton = wrapper
        .findAll('button')
        .find((button) => button.text() === 'コピー');
    await copyButton?.trigger('click');

    expect(axios.post).toHaveBeenCalledWith('/ui-pattern-snippets/42/copy');
});

test('a locked pattern never triggers a copy-tracking request (no copy button exists)', () => {
    mount(PatternCard, {
        props: {
            id: 99,
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc: '<!doctype html><html><body>x</body></html>',
        },
    });

    expect(axios.post).not.toHaveBeenCalled();
});
