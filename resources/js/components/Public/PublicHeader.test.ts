// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

import PublicHeader from './PublicHeader.vue';

const linkStub = { template: '<a><slot /></a>', props: ['href'] };

// PublicHeader.vue calls the global route() helper (registered at runtime
// by Ziggy's Vue plugin, resources/js/app.ts). Vitest's jsdom environment
// never installs that plugin, so route() must be stubbed or the component
// throws "route is not defined" on mount, failing every test in this file.
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

test('shows login and register links when the user is not logged in', () => {
    const wrapper = mount(PublicHeader, {
        props: { user: null },
        global: { stubs: { Link: linkStub } },
    });

    expect(wrapper.text()).toContain('ログイン');
    expect(wrapper.text()).toContain('新規登録');
    expect(wrapper.text()).not.toContain('マイページ');
});

test('shows the user menu instead of login/register links when logged in', () => {
    const wrapper = mount(PublicHeader, {
        props: { user: { name: '山田太郎' } },
        global: { stubs: { Link: linkStub } },
    });

    expect(wrapper.text()).toContain('山田太郎');
    expect(wrapper.text()).not.toContain('新規登録');
});

test('the company info dropdown links open in a new tab safely', () => {
    const wrapper = mount(PublicHeader, {
        props: { user: null },
        global: { stubs: { Link: linkStub } },
    });

    const katsucode = wrapper.find('a[href="https://katsucode.jp/"]');
    expect(katsucode.attributes('target')).toBe('_blank');
    expect(katsucode.attributes('rel')).toBe('noopener noreferrer');

    expect(wrapper.find('a[href="https://katsu-coach.com/"]').exists()).toBe(
        true,
    );
    expect(wrapper.find('a[href="https://smartsprouts.jp/"]').exists()).toBe(
        true,
    );
});
