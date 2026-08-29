// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import PublicFooter from './PublicFooter.vue';

test('renders all four navigation groups with the correct links', () => {
    const wrapper = mount(PublicFooter);

    const expectedHrefs = [
        '/about',
        '/service',
        '/premium',
        '/faq',
        'https://katsucode.jp/',
        'https://katsu-coach.com/',
        'https://smartsprouts.jp/',
        '/legal/privacy-policy',
        '/legal/terms',
        '/legal/cookie-policy',
        '/legal/tokushoho',
        '/company',
        '/contact',
        '/blog',
    ];

    for (const href of expectedHrefs) {
        expect(wrapper.find(`a[href="${href}"]`).exists()).toBe(true);
    }
});

test('external links open in a new tab safely', () => {
    const wrapper = mount(PublicFooter);

    const external = wrapper.find('a[href="https://katsucode.jp/"]');
    expect(external.attributes('target')).toBe('_blank');
    expect(external.attributes('rel')).toBe('noopener noreferrer');
});

test('shows the current year in the copyright line', () => {
    const wrapper = mount(PublicFooter);

    expect(wrapper.text()).toContain(`${new Date().getFullYear()}`);
    expect(wrapper.text()).toContain('katsuooool');
});
