// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const pageState = vi.hoisted(() => ({ url: '/' }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        url: pageState.url,
        props: { auth: { user: null }, toolNav },
    }),
    router: { get: () => {} },
}));

import Home from './Home.vue';

const tools = [
    {
        slug: 'image-compress',
        name: '画像圧縮',
        category: 'image',
        description: '画像を圧縮します。',
        href: '/image-compress',
    },
    {
        slug: 'image-resize',
        name: '画像リサイズ',
        category: 'image',
        description: '画像のサイズを変えます。',
        href: '/image-resize',
    },
    {
        slug: 'color-palette',
        name: 'カラーパレット生成',
        category: 'color',
        description: '色の組み合わせを作ります。',
        href: '/color-palette',
    },
];

const toolNav = tools.map(({ slug, name, category, href }) => ({
    slug,
    name,
    category,
    href,
}));

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
    pageState.url = '/';
});

function mountHome() {
    return mount(Home, { props: { tools } });
}

function cardNames(wrapper: ReturnType<typeof mountHome>): string[] {
    return wrapper.findAll('main h3').map((h3) => h3.text());
}

describe('Home', () => {
    test('shows every tool when no category is given', () => {
        const wrapper = mountHome();

        expect(wrapper.find('h1').text()).toContain('すべて');
        expect(cardNames(wrapper)).toHaveLength(3);
    });

    test('shows only the tools of the category in the URL', () => {
        pageState.url = '/?category=image';
        const wrapper = mountHome();

        expect(wrapper.find('h1').text()).toContain('画像');
        expect(cardNames(wrapper)).toEqual(['画像圧縮', '画像リサイズ']);
    });

    test('treats an unknown category as all', () => {
        pageState.url = '/?category=xxx';
        const wrapper = mountHome();

        expect(wrapper.find('h1').text()).toContain('すべて');
        expect(cardNames(wrapper)).toHaveLength(3);
    });

    test('filters by the search query and shows it', () => {
        pageState.url = '/?q=%E8%89%B2';
        const wrapper = mountHome();

        expect(cardNames(wrapper)).toEqual(['カラーパレット生成']);
        expect(wrapper.text()).toContain('「色」の検索結果');
    });

    test('shows the empty message when nothing matches', () => {
        pageState.url = '/?q=zzz';
        const wrapper = mountHome();

        expect(wrapper.text()).toContain('該当するツールが見つかりませんでした。');
    });

    test('does not show a search line for a whitespace-only query', () => {
        pageState.url = '/?q=%20%20';
        const wrapper = mountHome();

        expect(wrapper.text()).not.toContain('の検索結果');
        expect(cardNames(wrapper)).toHaveLength(3);
    });

    test('keeps working when the URL has a #fragment', () => {
        pageState.url = '/?category=color#top';
        const wrapper = mountHome();

        expect(cardNames(wrapper)).toEqual(['カラーパレット生成']);
    });

    test('highlights the active category in the sidebar', () => {
        pageState.url = '/?category=color';
        const wrapper = mountHome();
        const current = wrapper.findAll('a[aria-current="page"]');

        expect(current).toHaveLength(1);
        expect(current[0].text()).toContain('色');
    });
});
