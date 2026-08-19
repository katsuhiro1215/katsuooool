// @vitest-environment jsdom
import { describe, expect, test } from 'vitest';

import { formatHtml } from './htmlFormat';

describe('formatHtml — structure', () => {
    test('indents nested elements', () => {
        const input = '<div><p>hello</p></div>';

        expect(formatHtml(input)).toBe(
            '<div>\n  <p>\n    hello\n  </p>\n</div>',
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

describe('formatHtml — escaping (regression: previously re-injected live markup)', () => {
    test('re-escapes text that contains already-escaped markup instead of unescaping it', () => {
        // The DOM decodes entities on parse (textContent is the decoded
        // string); the formatter must re-encode them on the way back out,
        // or a harmless "&lt;script&gt;" in the source becomes a live
        // <script> tag in the output.
        const input = '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>';

        const output = formatHtml(input);

        expect(output).toBe(
            '<p>\n  &lt;script&gt;alert(1)&lt;/script&gt;\n</p>',
        );
        expect(output).not.toContain('<script>');
    });

    test('escapes bare & and < in ordinary text', () => {
        const input = '<p>a &lt; b &amp; c</p>';

        expect(formatHtml(input)).toBe('<p>\n  a &lt; b &amp; c\n</p>');
    });

    test('escapes " and & in attribute values', () => {
        const input = `<a title='say "hi"' data-x="a&amp;b">t</a>`;

        const output = formatHtml(input);

        expect(output).toContain('title="say &quot;hi&quot;"');
        expect(output).toContain('data-x="a&amp;b"');
    });
});

describe('formatHtml — whitespace-sensitive elements (regression: content silently reformatted)', () => {
    test('preserves internal whitespace and indentation inside <pre> verbatim', () => {
        const input = '<pre>line1\n  line2</pre>';

        expect(formatHtml(input)).toBe('<pre>line1\n  line2</pre>');
    });

    test('preserves leading/trailing whitespace inside <textarea> verbatim', () => {
        const input = '<textarea>  keep  </textarea>';

        expect(formatHtml(input)).toBe('<textarea>  keep  </textarea>');
    });

    test('does not escape or reformat <script> content', () => {
        // A bare <script> fragment is promoted into an implicit <head> by
        // the browser's own HTML parser, so the output is a full document
        // — the interesting assertion is that the script body survives
        // completely unescaped and unreformatted inside it.
        const input = '<script>if (a < b) { alert("x & y"); }</script>';

        expect(formatHtml(input)).toContain(
            '<script>if (a < b) { alert("x & y"); }</script>',
        );
    });

    test('does not escape or reformat <style> content', () => {
        const input = '<style>a::after{content:"<>"}</style>';

        expect(formatHtml(input)).toContain(
            '<style>a::after{content:"<>"}</style>',
        );
    });
});

describe('formatHtml — full documents (regression: <head> silently dropped)', () => {
    test('preserves doctype, title, meta, and head <style> for a full document', () => {
        const input =
            '<!DOCTYPE html><html><head><title>T</title><meta charset="utf-8"><style>a{color:red}</style></head><body><p>hi</p></body></html>';

        const output = formatHtml(input);

        expect(output).toContain('<!DOCTYPE html>');
        expect(output).toContain('<title>');
        expect(output).toContain('T');
        expect(output).toContain('<meta charset="utf-8">');
        expect(output).toContain('<style>a{color:red}</style>');
        expect(output).toContain('<p>');
        expect(output).toContain('hi');
    });

    test('still formats a plain fragment from <body> only (no <html>/<head> added)', () => {
        const input = '<p>hello</p>';

        expect(formatHtml(input)).toBe('<p>\n  hello\n</p>');
    });
});
