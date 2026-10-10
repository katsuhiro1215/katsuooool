// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({
        url: '/',
        props: { auth: { user: null }, toolNav: [] },
    }),
    router: { get: () => {} },
}));

import Company from './Company.vue';

// PublicLayout renders PublicHeader, which calls the global route() helper
// (registered at runtime by Ziggy's Vue plugin, resources/js/app.ts).
// Vitest's jsdom environment never installs that plugin, so route() must be
// stubbed or mounting throws "route is not defined".
// See resources/js/components/Public/PublicHeader.test.ts for the same pattern.
beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
});

describe('Company', () => {
    test('renders company info from props', () => {
        const wrapper = mount(Company, {
            props: {
                company: {
                    name: 'テスト株式会社',
                    representative: '山田太郎',
                    address: '東京都渋谷区1-1-1',
                    contact: 'contact@example.com',
                },
            },
        });

        expect(wrapper.text()).toContain('テスト株式会社');
        expect(wrapper.text()).toContain('山田太郎');
        expect(wrapper.text()).toContain('東京都渋谷区1-1-1');
        expect(wrapper.text()).toContain('contact@example.com');
    });

    test('falls back to a placeholder when a company field is unset', () => {
        const wrapper = mount(Company, {
            props: {
                company: {
                    name: null,
                    representative: null,
                    address: null,
                    contact: null,
                },
            },
        });

        expect(wrapper.text()).toContain('事業者名を入力してください');
    });
});
