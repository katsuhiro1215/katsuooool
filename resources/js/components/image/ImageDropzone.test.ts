// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import ImageDropzone from './ImageDropzone.vue';

const file = (name: string) => new File(['x'], name, { type: 'image/jpeg' });

test('emits the files chosen through the file input', async () => {
    const wrapper = mount(ImageDropzone, { props: { accept: 'image/jpeg' } });
    const input = wrapper.find('input[type="file"]');
    Object.defineProperty(input.element, 'files', {
        value: [file('a.jpg')],
        configurable: true,
    });

    await input.trigger('change');

    expect(wrapper.emitted('files')?.[0][0]).toHaveLength(1);
});

test('emits the dropped files and clears the dragging state', async () => {
    const wrapper = mount(ImageDropzone, { props: { accept: 'image/jpeg' } });

    await wrapper.trigger('dragover');
    expect(wrapper.classes().join(' ')).toContain('bg-gray-100');

    await wrapper.trigger('drop', {
        dataTransfer: { files: [file('a.jpg'), file('b.jpg')] },
    });

    expect(wrapper.emitted('files')?.[0][0]).toHaveLength(2);
    expect(wrapper.classes().join(' ')).not.toContain('bg-gray-100');
});

test('passes the accept list to the input and shows the hint', () => {
    const wrapper = mount(ImageDropzone, {
        props: { accept: 'image/png,.heic', hint: '最大10枚' },
    });

    expect(wrapper.find('input').attributes('accept')).toBe('image/png,.heic');
    expect(wrapper.text()).toContain('最大10枚');
});
