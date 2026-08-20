import { describe, expect, test } from 'vitest';

import { buildButton, buildCard } from './uiPatternGenerators';

describe('buildButton', () => {
    test('builds the HTML snippet with a fixed class name', () => {
        const result = buildButton({
            backgroundColor: '#2563eb',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 6,
            borderEnabled: false,
            borderColor: '#000000',
        });

        expect(result.html).toBe('<button class="btn">ボタン</button>');
    });

    test('builds the CSS rule with border: none when borderEnabled is false', () => {
        const result = buildButton({
            backgroundColor: '#2563eb',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 6,
            borderEnabled: false,
            borderColor: '#000000',
        });

        expect(result.css).toBe(
            '.btn {\n' +
                '    background-color: #2563eb;\n' +
                '    color: #ffffff;\n' +
                '    padding: 10px 20px;\n' +
                '    font-size: 16px;\n' +
                '    border-radius: 6px;\n' +
                '    border: none;\n' +
                '    cursor: pointer;\n' +
                '}',
        );
    });

    test('builds the CSS rule with a solid border when borderEnabled is true', () => {
        const result = buildButton({
            backgroundColor: '#2563eb',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 6,
            borderEnabled: true,
            borderColor: '#111111',
        });

        expect(result.css).toContain('border: 1px solid #111111;');
    });

    test.each([
        ['small', '6px 12px', '14px'],
        ['medium', '10px 20px', '16px'],
        ['large', '14px 28px', '18px'],
    ] as const)(
        'maps size "%s" to padding "%s" and font-size "%s"',
        (size, padding, fontSize) => {
            const result = buildButton({
                backgroundColor: '#000000',
                textColor: '#ffffff',
                size,
                borderRadius: 0,
                borderEnabled: false,
                borderColor: '#000000',
            });

            expect(result.css).toContain(`padding: ${padding};`);
            expect(result.css).toContain(`font-size: ${fontSize};`);
        },
    );

    test('reflects the border-radius value', () => {
        const result = buildButton({
            backgroundColor: '#000000',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 24,
            borderEnabled: false,
            borderColor: '#000000',
        });

        expect(result.css).toContain('border-radius: 24px;');
    });
});

describe('buildCard', () => {
    test('builds a plain card with title, text, and no image markup', () => {
        const result = buildCard({
            variant: 'plain',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            shadowEnabled: true,
            padding: 16,
            title: 'タイトル',
            text: '本文テキストがここに入ります。',
        });

        expect(result.html).toBe(
            '<div class="card">\n' +
                '    <h3 class="card-title">タイトル</h3>\n' +
                '    <p class="card-text">本文テキストがここに入ります。</p>\n' +
                '</div>',
        );
        expect(result.html).not.toContain('<img');
        expect(result.css).toContain('padding: 16px;');
    });

    test('builds a plain card with box-shadow: none when shadowEnabled is false', () => {
        const result = buildCard({
            variant: 'plain',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            shadowEnabled: false,
            padding: 16,
            title: 'タイトル',
            text: '本文',
        });

        expect(result.css).toContain('box-shadow: none;');
    });

    test('builds an image card with a placeholder <img> and a .card-body wrapper', () => {
        const result = buildCard({
            variant: 'image',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            shadowEnabled: true,
            padding: 16,
            title: 'タイトル',
            text: '本文',
        });

        expect(result.html).toContain(
            '<img class="card-image" src="https://placehold.co/400x200" alt="">',
        );
        expect(result.html).toContain('<div class="card-body">');
        expect(result.css).toContain('.card-image {');
        expect(result.css).toContain('.card-body {');
    });

    test('reflects the border-radius and shadow value for the image variant', () => {
        const result = buildCard({
            variant: 'image',
            backgroundColor: '#f5f5f5',
            borderRadius: 12,
            shadowEnabled: true,
            padding: 20,
            title: 'タイトル',
            text: '本文',
        });

        expect(result.css).toContain('border-radius: 12px;');
        expect(result.css).toContain(
            'box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);',
        );
        expect(result.css).toContain('padding: 20px;');
    });
});
