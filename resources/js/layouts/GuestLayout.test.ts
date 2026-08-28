// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import GuestLayout from './GuestLayout.vue';

test('renders the logo and the default slot content', () => {
    const wrapper = mount(GuestLayout, {
        slots: {
            default: '<p>ログインフォーム</p>',
        },
        global: {
            stubs: {
                Link: { template: '<a><slot /></a>' },
            },
        },
    });

    expect(wrapper.find('img').attributes('src')).toBe('/upload/logo.svg');
    expect(wrapper.text()).toContain('ログインフォーム');
});
