import { describe, expect, test } from 'vitest';

import {
    buildBoxShadow,
    buildBorderRadius,
    buildFlexbox,
    buildGlassmorphism,
    buildGrid,
} from './cssGenerators';

describe('buildBoxShadow', () => {
    test('builds a standard box-shadow declaration', () => {
        const result = buildBoxShadow({
            x: 4,
            y: 8,
            blur: 16,
            spread: 0,
            color: '#000000',
            inset: false,
        });

        expect(result).toBe('box-shadow: 4px 8px 16px 0px #000000;');
    });

    test('prefixes inset when inset is true', () => {
        const result = buildBoxShadow({
            x: 0,
            y: 0,
            blur: 10,
            spread: -2,
            color: '#ff0000',
            inset: true,
        });

        expect(result).toBe('box-shadow: inset 0px 0px 10px -2px #ff0000;');
    });
});

describe('buildBorderRadius', () => {
    test('outputs all four corners in top-left, top-right, bottom-right, bottom-left order', () => {
        const result = buildBorderRadius({
            topLeft: 4,
            topRight: 8,
            bottomRight: 12,
            bottomLeft: 16,
        });

        expect(result).toBe('border-radius: 4px 8px 12px 16px;');
    });

    test('does not collapse equal corners into a single value', () => {
        const result = buildBorderRadius({
            topLeft: 10,
            topRight: 10,
            bottomRight: 10,
            bottomLeft: 10,
        });

        expect(result).toBe('border-radius: 10px 10px 10px 10px;');
    });
});

describe('buildGlassmorphism', () => {
    test('builds the four-line glassmorphism CSS block', () => {
        const result = buildGlassmorphism({
            opacity: 0.2,
            blur: 10,
            borderOpacity: 0.3,
            shadowOpacity: 0.37,
        });

        expect(result).toBe(
            'background: rgba(255, 255, 255, 0.2);\n' +
                'backdrop-filter: blur(10px);\n' +
                'border: 1px solid rgba(255, 255, 255, 0.3);\n' +
                'box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);',
        );
    });
});

describe('buildFlexbox', () => {
    test('builds the six-line flexbox CSS block including defaults', () => {
        const result = buildFlexbox({
            direction: 'row',
            justifyContent: 'flex-start',
            alignItems: 'stretch',
            wrap: 'nowrap',
            gap: 0,
        });

        expect(result).toBe(
            'display: flex;\n' +
                'flex-direction: row;\n' +
                'justify-content: flex-start;\n' +
                'align-items: stretch;\n' +
                'flex-wrap: nowrap;\n' +
                'gap: 0px;',
        );
    });

    test('reflects non-default values', () => {
        const result = buildFlexbox({
            direction: 'column',
            justifyContent: 'space-between',
            alignItems: 'center',
            wrap: 'wrap',
            gap: 16,
        });

        expect(result).toContain('flex-direction: column;');
        expect(result).toContain('justify-content: space-between;');
        expect(result).toContain('gap: 16px;');
    });
});

describe('buildGrid', () => {
    test('builds the four-line grid CSS block using repeat()', () => {
        const result = buildGrid({ columns: 3, rows: 2, gap: 8 });

        expect(result).toBe(
            'display: grid;\n' +
                'grid-template-columns: repeat(3, 1fr);\n' +
                'grid-template-rows: repeat(2, 1fr);\n' +
                'gap: 8px;',
        );
    });
});
