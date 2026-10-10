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

import ImageRotate from './ImageRotate.vue';

// PublicLayout renders PublicHeader, which calls the global route() helper
// (registered at runtime by Ziggy's Vue plugin, resources/js/app.ts).
// Vitest's jsdom environment never installs that plugin, so route() must be
// stubbed or mounting throws "route is not defined".
// See resources/js/components/Public/PublicHeader.test.ts for the same pattern.
beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`);
    // jsdom does not implement createObjectURL/revokeObjectURL.
    vi.stubGlobal(
        'URL',
        Object.assign(URL, {
            createObjectURL: vi.fn(() => 'blob:mock-url'),
            revokeObjectURL: vi.fn(),
        }),
    );
});

function makeFile(name: string): File {
    return new File(['x'], name, { type: 'image/png' });
}

describe('ImageRotate', () => {
    test('shows a thumbnail preview for each selected file', async () => {
        const wrapper = mount(ImageRotate, { props: { maxFiles: 3 } });

        const input = wrapper.find('input[type="file"]');
        Object.defineProperty(input.element, 'files', {
            value: [makeFile('a.png'), makeFile('b.png')],
        });
        await input.trigger('change');

        const thumbnails = wrapper.findAll('img.rotate-preview');
        expect(thumbnails).toHaveLength(2);
    });

    test('rotates the thumbnail preview to match the selected direction', async () => {
        const wrapper = mount(ImageRotate, { props: { maxFiles: 3 } });

        const input = wrapper.find('input[type="file"]');
        Object.defineProperty(input.element, 'files', {
            value: [makeFile('a.png')],
        });
        await input.trigger('change');

        const thumbnail = wrapper.find('img.rotate-preview');
        expect(thumbnail.attributes('style')).toContain('rotate(-90deg)');

        await wrapper.find('input[type="radio"][value="180"]').setValue();
        expect(thumbnail.attributes('style')).toContain('rotate(180deg)');

        await wrapper.find('input[type="radio"][value="right"]').setValue();
        expect(thumbnail.attributes('style')).toContain('rotate(90deg)');
    });
});
