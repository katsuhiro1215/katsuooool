import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

const post = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({
    default: {
        post,
        isAxiosError: (e: unknown) =>
            typeof e === 'object' && e !== null && 'isAxiosError' in e,
    },
}));

import { useImageBatch } from './useImageBatch';

const created: string[] = [];
const revoked: string[] = [];

beforeEach(() => {
    post.mockReset();
    created.length = 0;
    revoked.length = 0;
    vi.stubGlobal('URL', {
        createObjectURL: (file: File) => {
            const url = `blob:${file.name}`;
            created.push(url);

            return url;
        },
        revokeObjectURL: (url: string) => revoked.push(url),
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

const img = (name: string, type = 'image/jpeg') =>
    new File(['x'], name, { type });

function makeBatch(maxFiles = 3) {
    return useImageBatch({
        endpoint: '/image-compress',
        maxFiles: () => maxFiles,
        fallbackError: '失敗しました',
    });
}

describe('useImageBatch', () => {
    test('starts empty, becomes ready after adding files', () => {
        const batch = makeBatch();
        expect(batch.phase.value).toBe('empty');

        batch.add([img('a.jpg')]);

        expect(batch.phase.value).toBe('ready');
        expect(batch.files.value).toHaveLength(1);
        expect(batch.files.value[0].previewUrl).toBe('blob:a.jpg');
    });

    test('adding more than the limit keeps the limit and shows a message', () => {
        const batch = makeBatch(2);

        batch.add([img('a.jpg'), img('b.jpg'), img('c.jpg')]);

        expect(batch.files.value).toHaveLength(2);
        expect(batch.errorMessage.value).toContain('最大2枚');
        expect(created).toEqual(['blob:a.jpg', 'blob:b.jpg']);
    });

    test('HEIC files get no preview URL', () => {
        const batch = makeBatch();

        batch.add([img('IMG_1.heic', 'image/heic')]);

        expect(batch.files.value[0].previewUrl).toBeNull();
    });

    test('removing a file revokes its preview URL', () => {
        const batch = makeBatch();
        batch.add([img('a.jpg'), img('b.jpg')]);

        batch.remove(batch.files.value[0].id);

        expect(batch.files.value.map((f) => f.file.name)).toEqual(['b.jpg']);
        expect(revoked).toEqual(['blob:a.jpg']);
    });

    test('clear revokes every preview URL and returns to empty', () => {
        const batch = makeBatch();
        batch.add([img('a.jpg'), img('b.jpg')]);

        batch.clear();

        expect(batch.phase.value).toBe('empty');
        expect(revoked.sort()).toEqual(['blob:a.jpg', 'blob:b.jpg']);
    });

    test('submit posts the files with the extra fields and ends in done', async () => {
        post.mockResolvedValue({
            data: {
                results: [
                    {
                        original_name: 'a.jpg',
                        status: 'success',
                        input_bytes: 1000,
                        output_bytes: 400,
                        reduction_percent: 60,
                        download_url: '/d/a',
                    },
                ],
                batch_download_url: null,
            },
        });
        const batch = makeBatch();
        batch.add([img('a.jpg')]);

        await batch.submit({ level: 'strong' });

        const [url, formData] = post.mock.calls[0];
        expect(url).toBe('/image-compress');
        expect((formData as FormData).get('level')).toBe('strong');
        expect((formData as FormData).getAll('files[]')).toHaveLength(1);
        expect(batch.phase.value).toBe('done');
        expect(batch.matched.value[0]?.status).toBe('success');
    });

    test('adding or removing a file after the run clears the results', async () => {
        post.mockResolvedValue({
            data: { results: [], batch_download_url: null },
        });
        const batch = makeBatch();
        batch.add([img('a.jpg')]);
        await batch.submit();
        expect(batch.phase.value).toBe('done');

        batch.add([img('b.jpg')]);

        expect(batch.phase.value).toBe('ready');
        expect(batch.response.value).toBeNull();
    });

    test('a failed request shows the resolved error and goes back to ready', async () => {
        post.mockRejectedValue(new Error('boom'));
        const batch = makeBatch();
        batch.add([img('a.jpg')]);

        await batch.submit();

        expect(batch.errorMessage.value).toBe('失敗しました');
        expect(batch.phase.value).toBe('ready');
        expect(batch.isProcessing.value).toBe(false);
    });

    test('submit does nothing without files', async () => {
        const batch = makeBatch();

        await batch.submit();

        expect(post).not.toHaveBeenCalled();
    });
});
