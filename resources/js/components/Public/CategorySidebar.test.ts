// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

import CategorySidebar from './CategorySidebar.vue';

const getMock = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { get: (...args: unknown[]) => getMock(...args) },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

const tools = [
    { slug: 'a', name: 'A', category: 'image', href: '/a' },
    { slug: 'b', name: 'B', category: 'image', href: '/b' },
    { slug: 'c', name: 'C', category: 'color', href: '/c' },
];

beforeEach(() => {
    getMock.mockClear();
});

function mountSidebar(
    props: Partial<{ activeCategory: string | null; query: string }> = {},
) {
    return mount(CategorySidebar, {
        props: { tools, activeCategory: 'all', query: '', ...props },
    });
}

test('lists all categories with counts and links', () => {
    const wrapper = mountSidebar();
    const links = wrapper.findAll('a');

    expect(links[0].attributes('href')).toBe('/');
    expect(links[0].text()).toContain('すべて');
    expect(links[0].text()).toContain('3');
    const image = links.find((link) => link.text().includes('画像'));
    expect(image?.attributes('href')).toBe('/?category=image');
    expect(image?.text()).toContain('2');
});

test('marks only the active category with aria-current', () => {
    const wrapper = mountSidebar({ activeCategory: 'color' });
    const current = wrapper.findAll('a[aria-current="page"]');

    expect(current).toHaveLength(1);
    expect(current[0].text()).toContain('色');
});

test('marks nothing as current when activeCategory is null', () => {
    const wrapper = mountSidebar({ activeCategory: null });

    expect(wrapper.findAll('a[aria-current="page"]')).toHaveLength(0);
});

test('submitting the search navigates to the home page with q', async () => {
    const wrapper = mountSidebar();

    await wrapper.find('input[type="search"]').setValue('画像&a #1');
    await wrapper.find('form').trigger('submit');

    expect(getMock).toHaveBeenCalledWith('/', { q: '画像&a #1' });
});

test('an empty search navigates to the home page without q', async () => {
    const wrapper = mountSidebar({ query: '圧縮' });

    await wrapper.find('input[type="search"]').setValue('   ');
    await wrapper.find('form').trigger('submit');

    expect(getMock).toHaveBeenCalledWith('/', {});
});

test('shows the current query in the search box', () => {
    const wrapper = mountSidebar({ query: '圧縮' });

    expect(
        (wrapper.find('input[type="search"]').element as HTMLInputElement)
            .value,
    ).toBe('圧縮');
});
