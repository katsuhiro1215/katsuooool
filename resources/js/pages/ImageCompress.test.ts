// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const post = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({
    default: { post, isAxiosError: () => false },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        url: '/image-compress',
        props: { auth: { user: null }, toolNav: [] },
    }),
    router: { get: () => {} },
}));

import ImageDropzone from '@/components/image/ImageDropzone.vue';
import ImageCompress from './ImageCompress.vue';

beforeEach(() => {
    post.mockReset();
    vi.stubGlobal('route', (name: string) => `/${name}`);
    // URL 全体は差し替えない（レイアウトが new URL() を使うため）
    Object.assign(URL, {
        createObjectURL: (f: File) => `blob:${f.name}`,
        revokeObjectURL: () => {},
    });
});

const file = (name: string, type = 'image/jpeg') =>
    new File(['x'], name, { type });

describe('ImageCompress', () => {
    test('starts with only the big dropzone', () => {
        const wrapper = mount(ImageCompress, { props: { maxFiles: 10 } });

        expect(wrapper.findComponent(ImageDropzone).exists()).toBe(true);
        expect(wrapper.text()).not.toContain('圧縮する');
    });

    test('after files are added it shows thumbnails and the level options with recommended selected', async () => {
        const wrapper = mount(ImageCompress, { props: { maxFiles: 10 } });

        wrapper
            .findComponent(ImageDropzone)
            .vm.$emit('files', [file('a.jpg'), file('b.png', 'image/png')]);
        await flushPromises();

        expect(wrapper.findAll('img[src^="blob:"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('2 / 10枚');
        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(3);
        expect(
            (
                wrapper.find('input[type="radio"][value="recommended"]')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(wrapper.text()).toContain('画質そのまま');
    });

    test('submitting posts the files and the chosen level, then shows results on the thumbnails', async () => {
        post.mockResolvedValue({
            data: {
                results: [
                    {
                        original_name: 'a.jpg',
                        status: 'success',
                        input_bytes: 4194304,
                        output_bytes: 1048576,
                        reduction_percent: 75,
                        download_url: '/d/a',
                        has_gps: true,
                    },
                ],
                batch_download_url: null,
            },
        });
        const wrapper = mount(ImageCompress, { props: { maxFiles: 10 } });
        wrapper.findComponent(ImageDropzone).vm.$emit('files', [file('a.jpg')]);
        await flushPromises();

        await wrapper
            .find('input[type="radio"][value="strong"]')
            .setValue(true);
        const submit = wrapper
            .findAll('button')
            .find((b) => b.text().includes('圧縮する'))!;
        await submit.trigger('click');
        await flushPromises();

        const formData = post.mock.calls[0][1] as FormData;
        expect(post.mock.calls[0][0]).toBe('/image-compress');
        expect(formData.get('level')).toBe('strong');
        expect(wrapper.text()).toContain('75%削減');
        expect(wrapper.text()).toContain('位置情報');
        expect(wrapper.text()).toContain('設定を変えてやり直す');
    });
});
