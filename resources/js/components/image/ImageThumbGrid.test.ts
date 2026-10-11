// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import type { ThumbItem } from '@/lib/imageBatch';
import ImageThumbGrid from './ImageThumbGrid.vue';

const item = (over: Partial<ThumbItem> = {}): ThumbItem => ({
    id: 'f1',
    name: 'photo.jpg',
    size: 2048,
    previewUrl: 'blob:photo',
    state: 'ready',
    result: null,
    ...over,
});

const mountGrid = (items: ThumbItem[], canAdd = true) =>
    mount(ImageThumbGrid, { props: { items, canAdd, accept: 'image/jpeg' } });

test('shows a preview image and the file size for a ready item', () => {
    const wrapper = mountGrid([item()]);

    expect(wrapper.find('img').attributes('src')).toBe('blob:photo');
    expect(wrapper.text()).toContain('photo.jpg');
    expect(wrapper.text()).toContain('2.0 KB');
});

test('an item without a preview (HEIC) shows a fallback with the file name', () => {
    const wrapper = mountGrid([item({ name: 'IMG_1.heic', previewUrl: null })]);

    expect(wrapper.find('img').exists()).toBe(false);
    expect(wrapper.find('[data-testid="thumb-fallback"]').text()).toContain(
        'IMG_1.heic',
    );
});

test('the remove button emits the item id', async () => {
    const wrapper = mountGrid([item()]);

    await wrapper.find('button[aria-label="削除"]').trigger('click');

    expect(wrapper.emitted('remove')?.[0]).toEqual(['f1']);
});

test('a processing item shows the overlay and no remove button', () => {
    const wrapper = mountGrid([item({ state: 'processing' })]);

    expect(wrapper.find('[data-testid="thumb-processing"]').exists()).toBe(
        true,
    );
    expect(wrapper.find('button[aria-label="削除"]').exists()).toBe(false);
});

test('a successful item shows before and after sizes, the download link and notes', () => {
    const wrapper = mountGrid([
        item({
            state: 'success',
            result: {
                original_name: 'photo.jpg',
                status: 'success',
                input_bytes: 4 * 1024 * 1024,
                output_bytes: 1024 * 1024,
                reduction_percent: 75,
                download_url: '/d/photo',
                has_gps: true,
                clamped: true,
            },
        }),
    ]);

    expect(wrapper.text()).toContain('4.0 MB');
    expect(wrapper.text()).toContain('1.0 MB');
    expect(wrapper.text()).toContain('75%削減');
    expect(wrapper.find('a[href="/d/photo"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('位置情報');
    expect(wrapper.text()).toContain('元のサイズに収めました');
});

test('a failed item shows the reason', () => {
    const wrapper = mountGrid([
        item({
            state: 'failed',
            result: {
                original_name: 'photo.jpg',
                status: 'failed',
                reason: '画像の処理に失敗しました。',
            },
        }),
    ]);

    expect(wrapper.text()).toContain('画像の処理に失敗しました。');
});

test('the add card appears only when more files can be added', () => {
    expect(mountGrid([item()], true).find('input[type="file"]').exists()).toBe(
        true,
    );
    expect(mountGrid([item()], false).find('input[type="file"]').exists()).toBe(
        false,
    );
});
