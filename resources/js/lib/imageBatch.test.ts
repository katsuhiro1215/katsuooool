import { describe, expect, test } from 'vitest';

import {
    formatBytes,
    isPreviewable,
    isSuccess,
    matchResults,
    summarize,
} from './imageBatch';
import type { BatchResult } from './imageBatch';

const ok = (name: string, input = 1000, output = 400): BatchResult => ({
    original_name: name,
    status: 'success',
    input_bytes: input,
    output_bytes: output,
    reduction_percent: Math.round((1 - output / input) * 100),
    download_url: `/d/${name}`,
});
const failed = (name: string): BatchResult => ({
    original_name: name,
    status: 'failed',
    reason: '失敗',
});

describe('isPreviewable', () => {
    test('browser-renderable image types are previewable', () => {
        expect(isPreviewable({ type: 'image/jpeg', name: 'a.jpg' })).toBe(true);
        expect(isPreviewable({ type: 'image/png', name: 'a.png' })).toBe(true);
        expect(isPreviewable({ type: 'image/webp', name: 'a.webp' })).toBe(
            true,
        );
    });

    test('HEIC is not previewable, with or without a MIME type', () => {
        expect(isPreviewable({ type: 'image/heic', name: 'a.heic' })).toBe(
            false,
        );
        expect(isPreviewable({ type: '', name: 'IMG_1.HEIC' })).toBe(false);
    });

    test('falls back to the extension when the MIME type is empty', () => {
        expect(isPreviewable({ type: '', name: 'photo.JPG' })).toBe(true);
    });
});

describe('formatBytes', () => {
    test('formats bytes, KB and MB', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(2048)).toBe('2.0 KB');
        expect(formatBytes(5 * 1024 * 1024)).toBe('5.0 MB');
    });
});

describe('matchResults', () => {
    test('matches results to files by name regardless of result order', () => {
        const matched = matchResults(
            ['a.jpg', 'b.jpg'],
            [failed('b.jpg'), ok('a.jpg')],
        );

        expect(matched[0]?.original_name).toBe('a.jpg');
        expect(matched[1]?.status).toBe('failed');
    });

    test('files with the same name each get their own result', () => {
        const matched = matchResults(
            ['photo.jpg', 'photo.jpg'],
            [ok('photo.jpg', 1000, 100), failed('photo.jpg')],
        );

        expect(isSuccess(matched[0])).toBe(true);
        expect(matched[1]?.status).toBe('failed');
    });

    test('a file without a result is null', () => {
        expect(matchResults(['a.jpg'], [])).toEqual([null]);
    });
});

describe('summarize', () => {
    test('sums only successful results', () => {
        const summary = summarize([
            ok('a', 1000, 400),
            ok('b', 3000, 600),
            failed('c'),
        ]);

        expect(summary).toEqual({
            count: 2,
            inputBytes: 4000,
            outputBytes: 1000,
            percent: 75,
        });
    });

    test('is all zeros when nothing succeeded', () => {
        expect(summarize([failed('a')])).toEqual({
            count: 0,
            inputBytes: 0,
            outputBytes: 0,
            percent: 0,
        });
    });
});
