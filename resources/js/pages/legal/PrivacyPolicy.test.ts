// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

import PrivacyPolicy from './PrivacyPolicy.vue';

// PublicLayout renders PublicHeader, which calls the global route() helper
// (registered at runtime by Ziggy's Vue plugin, resources/js/app.ts).
// Vitest's jsdom environment never installs that plugin, so route() must be
// stubbed or mounting throws "route is not defined".
// See resources/js/components/Public/PublicHeader.test.ts for the same pattern.
beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
});

describe('PrivacyPolicy', () => {
    test('renders the company name and contact from props', () => {
        const wrapper = mount(PrivacyPolicy, {
            props: {
                company: {
                    name: 'テスト株式会社',
                    representative: null,
                    address: null,
                    contact: 'contact@example.com',
                },
            },
        });

        expect(wrapper.text()).toContain('テスト株式会社');
        expect(wrapper.text()).toContain('contact@example.com');
    });

    test('falls back to placeholders when company fields are unset', () => {
        const wrapper = mount(PrivacyPolicy, {
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
        expect(wrapper.text()).toContain(
            'メールアドレス等の連絡先を入力してください',
        );
    });
});
