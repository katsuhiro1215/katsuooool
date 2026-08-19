import { describe, expect, test } from 'vitest';

import { checkDescriptionLength, checkTitleLength } from './seoTextCheck';

describe('checkTitleLength', () => {
    test('counts characters and bytes, and flags within the recommended range', () => {
        const result = checkTitleLength('短いタイトル');

        expect(result.characterCount).toBe(6);
        expect(result.withinRecommended).toBe(true);
    });

    test('flags a title over 35 characters as outside the recommended range', () => {
        const result = checkTitleLength('あ'.repeat(36));

        expect(result.characterCount).toBe(36);
        expect(result.withinRecommended).toBe(false);
    });

    test('counts UTF-8 byte length correctly for multi-byte characters', () => {
        const result = checkTitleLength('あ');

        expect(result.byteCount).toBe(3);
    });

    test('counts surrogate-pair characters (e.g. emoji) as a single character', () => {
        const result = checkTitleLength('😀');

        expect(result.characterCount).toBe(1);
    });
});

describe('checkDescriptionLength', () => {
    test('flags a description within 160 characters as within the recommended range', () => {
        const result = checkDescriptionLength('あ'.repeat(160));

        expect(result.withinRecommended).toBe(true);
    });

    test('flags a description over 160 characters as outside the recommended range', () => {
        const result = checkDescriptionLength('あ'.repeat(161));

        expect(result.withinRecommended).toBe(false);
    });

    test('returns zero counts for an empty string', () => {
        const result = checkDescriptionLength('');

        expect(result.characterCount).toBe(0);
        expect(result.byteCount).toBe(0);
        expect(result.withinRecommended).toBe(true);
    });
});
