// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({ props: { auth: { user: null } } }),
}));

import Premium from './Premium.vue';

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
});

describe('Premium', () => {
    test('shows a subscribe link for each plan when enabled', () => {
        const wrapper = mount(Premium, {
            props: { isPremium: false, enabled: true },
        });

        expect(wrapper.text()).not.toContain('近日公開');
        expect(wrapper.text()).toContain('アカウントを作成する');
    });

    test('shows a coming-soon notice instead of subscribe links when disabled', () => {
        const wrapper = mount(Premium, {
            props: { isPremium: false, enabled: false },
        });

        expect(wrapper.text()).toContain('近日公開');
        expect(wrapper.text()).not.toContain('アカウントを作成する');
        expect(wrapper.text()).not.toContain('このプランで登録');
    });
});
