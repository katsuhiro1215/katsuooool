// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import ApplicationLogo from './ApplicationLogo.vue';

test('renders the logo image with the correct source and alt text', () => {
    const wrapper = mount(ApplicationLogo);

    const img = wrapper.find('img');
    expect(img.attributes('src')).toBe('/upload/logo.svg');
    expect(img.attributes('alt')).toBe('katsuooool');
});
