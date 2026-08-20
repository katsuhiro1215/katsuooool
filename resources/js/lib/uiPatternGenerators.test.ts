import { describe, expect, test } from 'vitest';

import { buildButton } from './uiPatternGenerators';

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
