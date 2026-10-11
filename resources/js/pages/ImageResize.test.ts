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
        url: '/image-resize',
        props: { auth: { user: null }, toolNav: [] },
    }),
    router: { get: () => {} },
}));

import ImageDropzone from '@/components/image/ImageDropzone.vue';
import ImageResize from './ImageResize.vue';

beforeEach(() => {
    post.mockReset();
    post.mockResolvedValue({ data: { results: [], batch_download_url: null } });
    vi.stubGlobal('route', (name: string) => `/${name}`);
    // URL 全体は差し替えない（レイアウトが new URL() を使うため）
    Object.assign(URL, {
        createObjectURL: (f: File) => `blob:${f.name}`,
        revokeObjectURL: () => {},
    });
});

const file = (name: string) => new File(['x'], name, { type: 'image/jpeg' });

async function withFile() {
    const wrapper = mount(ImageResize, { props: { maxFiles: 10 } });
    wrapper.findComponent(ImageDropzone).vm.$emit('files', [file('a.jpg')]);
    await flushPromises();

    return wrapper;
}

const submitButton = (wrapper: Awaited<ReturnType<typeof withFile>>) =>
    wrapper.findAll('button').find((b) => b.text().includes('リサイズする'))!;

describe('ImageResize', () => {
    test('the submit button needs a width or a height in pixel mode', async () => {
        const wrapper = await withFile();

        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();

        await wrapper.find('input[name="width"]').setValue('800');

        expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    test('pixel mode posts mode, width and height', async () => {
        const wrapper = await withFile();
        await wrapper.find('input[name="width"]').setValue('800');

        await submitButton(wrapper).trigger('click');
        await flushPromises();

        const formData = post.mock.calls[0][1] as FormData;
        expect(post.mock.calls[0][0]).toBe('/image-resize');
        expect(formData.get('mode')).toBe('pixel');
        expect(formData.get('width')).toBe('800');
        expect(formData.get('height')).toBeNull();
    });

    test('percent mode posts the percentage (default 50) and no pixel sizes', async () => {
        const wrapper = await withFile();
        await wrapper.find('input[type="radio"][value="percent"]').setValue(true);

        expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
        await submitButton(wrapper).trigger('click');
        await flushPromises();

        const formData = post.mock.calls[0][1] as FormData;
        expect(formData.get('mode')).toBe('percent');
        expect(formData.get('percent')).toBe('50');
        expect(formData.get('width')).toBeNull();
    });

    test('a percentage outside 1 to 100 disables the submit button', async () => {
        const wrapper = await withFile();
        await wrapper.find('input[type="radio"][value="percent"]').setValue(true);

        await wrapper.find('input[name="percent"]').setValue('150');
        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();

        await wrapper.find('input[name="percent"]').setValue('0');
        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();
    });

    test('the pixel mode explains that images are never enlarged', async () => {
        const wrapper = await withFile();

        expect(wrapper.text()).toContain('拡大はできません');
    });
});
