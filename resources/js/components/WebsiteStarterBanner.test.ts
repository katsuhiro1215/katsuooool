// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import WebsiteStarterBanner from './WebsiteStarterBanner.vue';

describe('WebsiteStarterBanner', () => {
    test('links to /website-starter', () => {
        const wrapper = mount(WebsiteStarterBanner);

        const link = wrapper.find('a');
        expect(link.attributes('href')).toBe('/website-starter');
    });
});
