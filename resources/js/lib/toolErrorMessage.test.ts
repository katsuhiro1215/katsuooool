import { AxiosError, AxiosHeaders } from 'axios';
import { describe, expect, test } from 'vitest';

import { resolveErrorMessage } from './toolErrorMessage';

function makeAxiosError(status: number, data: unknown = undefined): AxiosError {
    const error = new AxiosError('Request failed');
    error.response = {
        status,
        statusText: '',
        headers: {},
        config: { headers: new AxiosHeaders() },
        data,
    };

    return error;
}

describe('resolveErrorMessage', () => {
    test('a 422 on the files key itself is a real batch-count error', () => {
        const error = makeAxiosError(422, {
            errors: {
                files: ['files の項目数は、3 個以下である必要があります。'],
            },
        });

        const result = resolveErrorMessage(error, 10, 'fallback');

        expect(result.message).toContain('無料プランでは一度に10枚まで');
        expect(result.showUpsell).toBe(true);
    });

    test('a 422 on a per-index files key is an upload failure, not a batch-count error', () => {
        const error = makeAxiosError(422, {
            errors: { 'files.0': ['files.0のアップロードに失敗しました。'] },
        });

        const result = resolveErrorMessage(error, 1, 'fallback');

        expect(result.message).not.toContain('無料プランでは一度に');
        expect(result.message).toContain('アップロードに失敗しました');
        expect(result.showUpsell).toBe(false);
    });

    test('a 429 for a free-tier user shows the premium upsell', () => {
        const error = makeAxiosError(429);

        const result = resolveErrorMessage(error, 10, 'fallback');

        expect(result.message).toContain('リクエストが多すぎます');
        expect(result.showUpsell).toBe(true);
    });

    test('a non-axios error falls back to the generic message', () => {
        const result = resolveErrorMessage(new Error('boom'), 3, 'fallback');

        expect(result.message).toBe('fallback');
        expect(result.showUpsell).toBe(false);
    });

    test('the 422 batch-count message mentions the premium limit of 20', () => {
        const error = makeAxiosError(422, {
            errors: {
                files: ['files の項目数は、10 個以下である必要があります。'],
            },
        });

        const result = resolveErrorMessage(error, 10, 'fallback');

        expect(result.message).toContain('20枚まで');
        expect(result.showUpsell).toBe(true);
    });

    test('a 422 batch-count error at the premium limit of 20 shows no upsell', () => {
        const error = makeAxiosError(422, {
            errors: {
                files: ['files の項目数は、20 個以下である必要があります。'],
            },
        });

        const result = resolveErrorMessage(error, 20, 'fallback');

        expect(result.message).toBe('ファイル数が多すぎます。');
        expect(result.showUpsell).toBe(false);
    });

    test('a 503 means the server is busy and shows no upsell', () => {
        const error = makeAxiosError(503, {
            message:
                '現在アクセスが集中しています。少し待ってから、もう一度お試しください。',
        });

        const result = resolveErrorMessage(error, 10, 'fallback');

        expect(result.message).toContain('アクセスが集中');
        expect(result.showUpsell).toBe(false);
    });

    test('a free user hitting the AVIF batch limit sees the AVIF limit, not the Premium 20', () => {
        const error = makeAxiosError(422, {
            errors: {
                files: ['files の項目数は、2 個以下である必要があります。'],
            },
        });

        const result = resolveErrorMessage(error, 10, 'fallback', {
            avifLimit: 2,
        });

        expect(result.message).toContain('AVIF');
        expect(result.message).toContain('2枚');
        expect(result.message).not.toContain('20枚');
        expect(result.showUpsell).toBe(true);
    });

    test('a Premium user hitting the AVIF batch limit gets no upsell', () => {
        const error = makeAxiosError(422, {
            errors: {
                files: ['files の項目数は、5 個以下である必要があります。'],
            },
        });

        const result = resolveErrorMessage(error, 20, 'fallback', {
            avifLimit: 5,
        });

        expect(result.message).toContain('AVIF');
        expect(result.message).toContain('5枚');
        expect(result.showUpsell).toBe(false);
    });

    test('a 429 for a Premium user during AVIF conversion shows no upsell', () => {
        const result = resolveErrorMessage(
            makeAxiosError(429),
            20,
            'fallback',
            {
                avifLimit: 5,
            },
        );

        expect(result.showUpsell).toBe(false);
    });
});
