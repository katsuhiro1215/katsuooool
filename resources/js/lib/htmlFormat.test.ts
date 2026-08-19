// @vitest-environment jsdom
import { describe, expect, test } from 'vitest';

import { formatHtml } from './htmlFormat';

describe('formatHtml', () => {
    test('indents nested elements', () => {
        const input = '<div><p>hello</p></div>';

        expect(formatHtml(input)).toBe(
            '<div>\n  <p>\n    hello\n  </p>\n</div>',
        );
    });

    test('preserves attributes', () => {
        const input = '<a href="/foo" class="link">text</a>';

        expect(formatHtml(input)).toBe(
            '<a href="/foo" class="link">\n  text\n</a>',
        );
    });

    test('formats void elements on a single line with no closing tag', () => {
        const input = '<div><img src="a.png"><br></div>';

        expect(formatHtml(input)).toBe(
            '<div>\n  <img src="a.png">\n  <br>\n</div>',
        );
    });

    test('collapses an empty element to a single line', () => {
        const input = '<div></div>';

        expect(formatHtml(input)).toBe('<div></div>');
    });

    test('does not throw on malformed HTML — the parser repairs it', () => {
        const input = '<div><p>unclosed';

        expect(() => formatHtml(input)).not.toThrow();
        expect(formatHtml(input)).toContain('unclosed');
    });

    test('formats a realistic multi-level document fragment', () => {
        const input = '<ul><li>one</li><li>two</li></ul><p>after</p>';

        expect(formatHtml(input)).toBe(
            '<ul>\n  <li>\n    one\n  </li>\n  <li>\n    two\n  </li>\n</ul>\n<p>\n  after\n</p>',
        );
    });
});
