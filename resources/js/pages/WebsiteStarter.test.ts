// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

vi.mock('axios');

import WebsiteStarter from './WebsiteStarter.vue';

// PublicLayout renders PublicHeader, which calls the global route() helper
// (registered at runtime by Ziggy's Vue plugin, resources/js/app.ts).
// Vitest's jsdom environment never installs that plugin, so route() must be
// stubbed or mounting throws "route is not defined".
// See resources/js/components/Public/PublicHeader.test.ts for the same pattern.
beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', (name: string) => {
        const routes: Record<string, string> = {
            login: '/login',
            register: '/register',
            'mypage.index': '/mypage',
            logout: '/logout',
        };

        return routes[name] ?? `/${name}`;
    });
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
});

const baseProps = {
    industries: { beauty: '美容・サロン', corporate: '企業サイト' },
    prefectures: ['奈良県', '東京都'],
    targetAgeRanges: { '20s': '20代', all: '指定なし' },
    targetGenders: { female: '女性', all: '指定なし' },
    purposes: { new_customers: '新規顧客獲得' },
};

describe('WebsiteStarter', () => {
    test('shows a character counter for the meta description field', async () => {
        const wrapper = mount(WebsiteStarter, { props: baseProps });

        const textarea = wrapper.find('textarea#meta-description');
        await textarea.setValue('奈良市の美容室です。');

        expect(wrapper.text()).toContain('文字数');
    });

    test('renders an option for every industry passed in props', () => {
        const wrapper = mount(WebsiteStarter, { props: baseProps });

        const options = wrapper.findAll('select#industry option');
        expect(options.map((option) => option.text())).toContain(
            '美容・サロン',
        );
        expect(options.map((option) => option.text())).toContain('企業サイト');
    });

    test('the generate button stays disabled until required fields are filled', async () => {
        const wrapper = mount(WebsiteStarter, { props: baseProps });

        const button = wrapper.find('button[type="button"].generate-button');
        expect(button.attributes('disabled')).toBeDefined();
    });

    test('the copy buttons write the generated content to the clipboard', async () => {
        const generatedMetaTags =
            '<meta property="og:title" content="奈良県の美容・サロン｜株式会社カツコード">';
        const generatedHtmlHead =
            '<title>奈良県の美容・サロン｜株式会社カツコード</title>';
        vi.mocked(axios.post).mockResolvedValue({
            data: {
                status: 'success',
                title: '奈良県の美容・サロン｜株式会社カツコード',
                favicon_download_url: '/tool-download/favicon-token',
                ogp_image_download_url: '/tool-download/ogp-token',
                meta_tags: generatedMetaTags,
                html_head: generatedHtmlHead,
            },
        });

        const wrapper = mount(WebsiteStarter, { props: baseProps });

        await wrapper.find('input#site-name').setValue('株式会社カツコード');
        await wrapper.find('input#site-url').setValue('https://example.com');
        await wrapper.find('select#industry').setValue('beauty');
        await wrapper.find('select#prefecture').setValue('奈良県');
        await wrapper
            .find('textarea#meta-description')
            .setValue('奈良市の美容室です。');

        const logoInput = wrapper.find('input#logo');
        const logoFile = new File(['logo'], 'logo.png', {
            type: 'image/png',
        });
        Object.defineProperty(logoInput.element, 'files', {
            value: [logoFile],
        });
        await logoInput.trigger('change');

        const generateButton = wrapper.find(
            'button[type="button"].generate-button',
        );
        expect(generateButton.attributes('disabled')).toBeUndefined();
        await generateButton.trigger('click');
        await flushPromises();

        const copyButtons = wrapper
            .findAll('button')
            .filter((button) => button.text().includes('コピー'));
        expect(copyButtons).toHaveLength(3);

        await copyButtons[0].trigger('click');
        expect(navigator.clipboard.writeText).toHaveBeenLastCalledWith(
            '奈良県の美容・サロン｜株式会社カツコード',
        );

        await copyButtons[1].trigger('click');
        expect(navigator.clipboard.writeText).toHaveBeenLastCalledWith(
            generatedMetaTags,
        );

        await copyButtons[2].trigger('click');
        expect(navigator.clipboard.writeText).toHaveBeenLastCalledWith(
            generatedHtmlHead,
        );
    });
});
