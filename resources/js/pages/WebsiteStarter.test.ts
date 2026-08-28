// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

import WebsiteStarter from './WebsiteStarter.vue';

// PublicLayout renders PublicHeader, which calls the global route() helper
// (registered at runtime by Ziggy's Vue plugin, resources/js/app.ts).
// Vitest's jsdom environment never installs that plugin, so route() must be
// stubbed or mounting throws "route is not defined".
// See resources/js/components/Public/PublicHeader.test.ts for the same pattern.
beforeEach(() => {
    vi.stubGlobal('route', (name: string) => {
        const routes: Record<string, string> = {
            login: '/login',
            register: '/register',
            'mypage.index': '/mypage',
            logout: '/logout',
        };

        return routes[name] ?? `/${name}`;
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
});
