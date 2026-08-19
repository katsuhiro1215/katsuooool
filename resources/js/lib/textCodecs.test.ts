import { describe, expect, test } from 'vitest';

import { decodeBase64, decodeUrl, encodeBase64, encodeUrl } from './textCodecs';

describe('URL encode/decode', () => {
    test('encodes reserved and non-ASCII characters', () => {
        expect(encodeUrl('a b&c=日本語')).toBe(
            'a%20b%26c%3D%E6%97%A5%E6%9C%AC%E8%AA%9E',
        );
    });

    test('decodes back to the original string', () => {
        const encoded = encodeUrl('a b&c=日本語');
        expect(decodeUrl(encoded)).toEqual({
            success: true,
            output: 'a b&c=日本語',
        });
    });

    test('returns a failure result for a malformed percent-encoded string', () => {
        const result = decodeUrl('%E0%A4%A');
        expect(result.success).toBe(false);
    });
});

describe('Base64 encode/decode', () => {
    test('round-trips ASCII text', () => {
        const encoded = encodeBase64('hello world');
        expect(encoded).toBe('aGVsbG8gd29ybGQ=');
        expect(decodeBase64(encoded)).toEqual({
            success: true,
            output: 'hello world',
        });
    });

    test('round-trips multi-byte UTF-8 text without corruption', () => {
        const original = '日本語のテキスト🎉';
        const encoded = encodeBase64(original);
        expect(decodeBase64(encoded)).toEqual({
            success: true,
            output: original,
        });
    });

    test('returns a failure result for an invalid Base64 string', () => {
        const result = decodeBase64('not-valid-base64!!!');
        expect(result.success).toBe(false);
    });
});
