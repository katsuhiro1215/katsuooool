import { expect, test } from 'vitest';

import { buildPatternDocument } from './uiPatternSnippet';
import type { PatternSnippet } from './uiPatternSnippet';

test('buildPatternDocument produces the exact wrapper document byte-for-byte', () => {
    // NOTE: This expected string must be kept in exact structural sync with
    // tests/Unit/Services/PatternDocumentRendererTest.php's expected string.
    // If you change the wrapper template in either buildPatternDocument()
    // or PatternDocumentRenderer::build(), update both tests' expected strings.
    const result = buildPatternDocument(
        '<a href="#">Button</a>',
        'a { color: red; }',
    );

    const expected = `<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<style>
  html, body {
    margin: 0;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    box-sizing: border-box;
  }
  a { color: red; }
</style>
</head>
<body>
<a href="#">Button</a>
</body>
</html>`;

    expect(result).toBe(expected);
});

test('PatternSnippet accepts both locked and unlocked shapes', () => {
    const unlocked: PatternSnippet = {
        id: 1,
        title: 'サンプル',
        locked: false,
        html: '<a>a</a>',
        css: 'a {}',
    };
    const locked: PatternSnippet = {
        id: 2,
        title: 'Premium',
        locked: true,
        srcdoc: '<html></html>',
    };

    expect(unlocked.locked).toBe(false);
    expect(locked.locked).toBe(true);
});
