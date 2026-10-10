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

import Terms from './Terms.vue';

// PublicLayout renders PublicHeader, which calls the global route() helper
// (registered at runtime by Ziggy's Vue plugin, resources/js/app.ts).
// Vitest's jsdom environment never installs that plugin, so route() must be
// stubbed or mounting throws "route is not defined".
// See resources/js/components/Public/PublicHeader.test.ts for the same pattern.
beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
});

describe('Terms', () => {
    test('renders the company name from props in the first article', () => {
        const wrapper = mount(Terms, {
            props: {
                company: {
                    name: 'テスト株式会社',
                    representative: null,
                    address: null,
                    contact: null,
                },
            },
        });

        expect(wrapper.text()).toContain('テスト株式会社');
    });

    test('falls back to a placeholder when the company name is unset', () => {
        const wrapper = mount(Terms, {
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
