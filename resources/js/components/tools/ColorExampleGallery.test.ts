// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, expect, test, vi } from 'vitest';

import ColorExampleGallery from './ColorExampleGallery.vue';

vi.mock('axios');

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: number) => {
        return `/${name.replace('.examples.copy', '')}/examples/${params}/copy`;
    });
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
    vi.mocked(axios.post).mockResolvedValue({ data: { status: 'ok' } });
});

test('renders nothing when there are no examples', () => {
    const wrapper = mount(ColorExampleGallery, {
        props: { examples: [], type: 'palette' },
    });

    expect(wrapper.find('section').exists()).toBe(false);
});

test('renders a palette example with its swatches', () => {
    const wrapper = mount(ColorExampleGallery, {
        props: {
            examples: [
                { id: 1, title: '夏のパレット', colors: ['#3366ff', '#ff9933'], angle: null, copy_count: 3 },
            ],
            type: 'palette',
        },
    });

    expect(wrapper.text()).toContain('夏のパレット');
    expect(wrapper.findAll('[style*="background-color"]')).toHaveLength(2);
});

test('copying a palette example writes a comma-separated hex string and tracks the copy', async () => {
    const wrapper = mount(ColorExampleGallery, {
        props: {
            examples: [
                { id: 7, title: '夏のパレット', colors: ['#3366ff', '#ff9933'], angle: null, copy_count: 0 },
            ],
            type: 'palette',
        },
    });

    const copyButton = wrapper
        .findAll('button')
        .find((button) => button.text().includes('コピー'));
    await copyButton?.trigger('click');

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('#3366ff, #ff9933');
    expect(axios.post).toHaveBeenCalledWith('/color-palette/examples/7/copy');
});

test('copying a gradient example writes a CSS background declaration', async () => {
    const wrapper = mount(ColorExampleGallery, {
        props: {
            examples: [
                { id: 9, title: '夕焼け', colors: ['#3366ff', '#ff9933'], angle: 90, copy_count: 0 },
            ],
            type: 'gradient',
        },
    });

    const copyButton = wrapper
        .findAll('button')
        .find((button) => button.text().includes('コピー'));
    await copyButton?.trigger('click');

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        'background: linear-gradient(90deg, #3366ff, #ff9933);',
    );
    expect(axios.post).toHaveBeenCalledWith('/color-gradient/examples/9/copy');
});
