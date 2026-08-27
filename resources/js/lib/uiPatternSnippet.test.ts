import { expect, test } from 'vitest';

import { buildPatternDocument } from './uiPatternSnippet';
import type { PatternSnippet } from './uiPatternSnippet';

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
