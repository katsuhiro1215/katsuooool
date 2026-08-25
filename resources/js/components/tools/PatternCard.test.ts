// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import PatternCard from './PatternCard.vue';

const html = '<a href="#">Button</a>';
const css = 'a { color: red; }';

test('switches the displayed code from HTML to CSS when the CSS tab is clicked', async () => {
    const wrapper = mount(PatternCard, {
        props: { title: 'サンプルボタン', html, css },
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
        props: { title: 'サンプルボタン', html, css },
    });

    const srcdoc = wrapper.find('iframe').attributes('srcdoc');
    expect(srcdoc).toContain(css);
});
