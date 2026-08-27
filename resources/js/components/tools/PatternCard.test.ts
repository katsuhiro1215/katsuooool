// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import PatternCard from './PatternCard.vue';

const html = '<a href="#">Button</a>';
const css = 'a { color: red; }';

test('switches the displayed code from HTML to CSS when the CSS tab is clicked', async () => {
    const wrapper = mount(PatternCard, {
        props: { title: 'サンプルボタン', locked: false, html, css },
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
        props: { title: 'サンプルボタン', locked: false, html, css },
    });

    const srcdoc = wrapper.find('iframe').attributes('srcdoc');
    expect(srcdoc).toContain(css);
});

test('a locked pattern shows the server-provided srcdoc without any code tabs or copy button', () => {
    const wrapper = mount(PatternCard, {
        props: {
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
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc:
                '<!doctype html><html><body>locked preview</body></html>',
        },
    });

    const upsellLink = wrapper.find('a[href="/premium"]');
    expect(upsellLink.exists()).toBe(true);
});
