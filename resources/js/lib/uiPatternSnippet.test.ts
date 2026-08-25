import { expect, test } from 'vitest';

import { buildPatternDocument } from './uiPatternSnippet';

test('embeds the given html inside the body and the css inside a style tag', () => {
    const result = buildPatternDocument(
        '<a href="#">Button</a>',
        'a { color: red; }',
    );

    expect(result).toContain('<a href="#">Button</a>');
    expect(result).toContain('a { color: red; }');
    expect(result).toContain('<style>');
    expect(result).toContain('<!doctype html>');
});
