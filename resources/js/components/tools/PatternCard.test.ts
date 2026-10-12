// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';

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

const wrappers: { unmount: () => void }[] = [];

afterEach(() => {
    while (wrappers.length > 0) {
        wrappers.pop()?.unmount();
    }
});

function mountAttached(props: Record<string, unknown> = {}) {
    Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
        configurable: true,
        get: () => 300,
    });
    Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
        configurable: true,
        get: () => 200,
    });
    const wrapper = mount(PatternCard, {
        props: { id: 1, title: 'サンプル', locked: false, html, css, ...props },
        attachTo: document.body,
    });
    wrappers.push(wrapper);

    return wrapper;
}

function sendSize(
    iframe: HTMLIFrameElement,
    width: number,
    height: number,
    source: unknown = iframe.contentWindow,
) {
    window.dispatchEvent(
        new MessageEvent('message', {
            data: { type: 'pattern-preview-size', width, height },
            source: source as MessageEventSource,
        }),
    );
}

test('the preview iframe is sandboxed with allow-scripts only (no same-origin)', () => {
    const wrapper = mountAttached();
    const sandbox = wrapper.find('iframe').attributes('sandbox');

    expect(sandbox).toBe('allow-scripts');
});

test('a size message from its own iframe scales a tall pattern down to fit the box', async () => {
    const wrapper = mountAttached();
    const iframe = wrapper.find('iframe').element as HTMLIFrameElement;

    // 内容 600x400 を 300x200 の枠に収める → 倍率 0.5
    sendSize(iframe, 600, 400);
    await wrapper.vm.$nextTick();

    const style = wrapper.find('iframe').attributes('style') ?? '';
    expect(style).toContain('width: 600px');
    expect(style).toContain('height: 400px');
    expect(style).toContain('scale(0.5)');
});

test('a small pattern is never enlarged', async () => {
    const wrapper = mountAttached();
    const iframe = wrapper.find('iframe').element as HTMLIFrameElement;

    sendSize(iframe, 120, 60);
    await wrapper.vm.$nextTick();

    expect(wrapper.find('iframe').attributes('style')).toContain('scale(1)');
});

test('messages from another window or with a malformed payload are ignored', async () => {
    const wrapper = mountAttached();
    const iframe = wrapper.find('iframe').element as HTMLIFrameElement;

    sendSize(iframe, 600, 400, window);
    window.dispatchEvent(
        new MessageEvent('message', {
            data: { type: 'pattern-preview-size', width: 'x', height: -5 },
            source: iframe.contentWindow as MessageEventSource,
        }),
    );
    window.dispatchEvent(
        new MessageEvent('message', {
            data: 'hello',
            source: iframe.contentWindow as MessageEventSource,
        }),
    );
    await wrapper.vm.$nextTick();

    expect(wrapper.find('iframe').attributes('style') ?? '').not.toContain(
        'scale(',
    );
});

test('huge sizes are clamped', async () => {
    const wrapper = mountAttached();
    const iframe = wrapper.find('iframe').element as HTMLIFrameElement;

    sendSize(iframe, 999999, 999999);
    await wrapper.vm.$nextTick();

    const style = wrapper.find('iframe').attributes('style') ?? '';
    expect(style).toContain('width: 4000px');
    expect(style).toContain('height: 4000px');
});

test('without any size message the iframe fills the box as before', () => {
    const wrapper = mountAttached();

    expect(wrapper.find('iframe').classes()).toContain('h-full');
    expect(wrapper.find('iframe').attributes('style') ?? '').not.toContain(
        'scale(',
    );
});

test('the message listener is removed when the card is unmounted', () => {
    const remove = vi.spyOn(window, 'removeEventListener');
    const wrapper = mountAttached();
    wrapper.unmount();
    wrappers.pop();

    expect(remove).toHaveBeenCalledWith('message', expect.any(Function));
});
