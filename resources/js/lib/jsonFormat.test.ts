import { describe, expect, test } from 'vitest';

import { formatJson } from './jsonFormat';

describe('formatJson', () => {
    test('pretty-prints a valid JSON object with 2-space indent by default', () => {
        const result = formatJson('{"b":2,"a":1}');

        expect(result).toEqual({
            success: true,
            output: '{\n  "b": 2,\n  "a": 1\n}',
        });
    });

    test('preserves key order (does not sort keys)', () => {
        const result = formatJson('{"z":1,"a":2}');

        expect(result.success && result.output.indexOf('"z"')).toBeLessThan(
            result.success ? result.output.indexOf('"a"') : -1,
        );
    });

    test('formats arrays and nested structures', () => {
        const result = formatJson('[1,2,{"a":[true,false,null]}]');

        expect(result).toEqual({
            success: true,
            output: '[\n  1,\n  2,\n  {\n    "a": [\n      true,\n      false,\n      null\n    ]\n  }\n]',
        });
    });

    test('returns a failure result with a message for malformed JSON', () => {
        const result = formatJson('{invalid}');

        expect(result.success).toBe(false);
        expect(!result.success && result.error.length).toBeGreaterThan(0);
    });

    test('returns a failure result for an empty string', () => {
        const result = formatJson('');

        expect(result.success).toBe(false);
    });

    test('accepts a custom indent width', () => {
        const result = formatJson('{"a":1}', 4);

        expect(result).toEqual({ success: true, output: '{\n    "a": 1\n}' });
    });
});
