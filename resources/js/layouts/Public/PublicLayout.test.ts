// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const pageState = vi.hoisted(() => ({ url: '/' }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        url: pageState.url,
        props: {
            auth: { user: null },
            toolNav: [
                {
                    slug: 'image-compress',
                    name: '画像圧縮',
                    category: 'image',
                    href: '/image-compress',
                },
            ],
        },
    }),
    router: { get: () => {} },
}));

import PublicLayout from './PublicLayout.vue';

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
    pageState.url = '/';
});

function mountLayout() {
    return mount(PublicLayout, { slots: { default: '<p>本文</p>' } });
}

describe('PublicLayout', () => {
    test('renders the slot inside main next to the sidebar', () => {
        const wrapper = mountLayout();

        expect(wrapper.find('main').text()).toContain('本文');
        expect(wrapper.find('nav[aria-label="カテゴリ"]').exists()).toBe(true);
    });

    test('highlights the category of the current tool page', () => {
        pageState.url = '/image-compress';
        const wrapper = mountLayout();
        const current = wrapper.findAll('a[aria-current="page"]');

        expect(current).toHaveLength(1);
        expect(current[0].text()).toContain('画像');
    });

    test('highlights nothing on pages that are not tools', () => {
        pageState.url = '/faq';
        const wrapper = mountLayout();

        expect(wrapper.findAll('a[aria-current="page"]')).toHaveLength(0);
    });

    test('toggles the sidebar panel on small screens', async () => {
        const wrapper = mountLayout();
        const button = wrapper.find('button[aria-expanded]');

        expect(button.attributes('aria-expanded')).toBe('false');
        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('true');
    });
});
