import { describe, expect, test } from 'vitest';

import {
    buildCheckerboardPattern,
    buildChevronPattern,
    buildDotPattern,
    buildGridPattern,
    buildStripePattern,
    buildWavePattern,
} from './patternGenerators';

describe('buildDotPattern', () => {
    test('builds a radial-gradient dot pattern', () => {
        const result = buildDotPattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            radius: 3,
            spacing: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: radial-gradient(circle, #000000 3px, transparent 3px);\n' +
                'background-size: 20px 20px;',
        );
    });
});

describe('buildGridPattern', () => {
    test('builds a two-gradient grid pattern', () => {
        const result = buildGridPattern({
            backgroundColor: '#ffffff',
            lineColor: '#000000',
            lineWidth: 1,
            cellSize: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: linear-gradient(to right, #000000 1px, transparent 1px), linear-gradient(to bottom, #000000 1px, transparent 1px);\n' +
                'background-size: 20px 20px;',
        );
    });
});

describe('buildStripePattern', () => {
    test('builds a repeating-linear-gradient stripe pattern', () => {
        const result = buildStripePattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            stripeWidth: 10,
            angle: 45,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: repeating-linear-gradient(45deg, #000000 0px 10px, #ffffff 10px 20px);',
        );
    });

    test('reflects a different angle and stripe width', () => {
        const result = buildStripePattern({
            backgroundColor: '#fafafa',
            patternColor: '#333333',
            stripeWidth: 5,
            angle: 90,
        });

        expect(result).toContain(
            'repeating-linear-gradient(90deg, #333333 0px 5px, #fafafa 5px 10px)',
        );
    });
});

describe('buildCheckerboardPattern', () => {
    test('builds a two-gradient checkerboard pattern', () => {
        const result = buildCheckerboardPattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            cellSize: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: linear-gradient(45deg, #000000 25%, transparent 25%, transparent 75%, #000000 75%), linear-gradient(45deg, #000000 25%, transparent 25%, transparent 75%, #000000 75%);\n' +
                'background-size: 40px 40px;\n' +
                'background-position: 0 0, 20px 20px;',
        );
    });
});

describe('buildChevronPattern', () => {
    test('builds a four-gradient chevron pattern', () => {
        const result = buildChevronPattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            width: 40,
            height: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: linear-gradient(135deg, #000000 25%, transparent 25%), linear-gradient(225deg, #000000 25%, transparent 25%), linear-gradient(315deg, #000000 25%, transparent 25%), linear-gradient(45deg, #000000 25%, transparent 25%);\n' +
                'background-size: 40px 20px;',
        );
    });
});

describe('buildWavePattern', () => {
    test('builds an SVG data URI wave pattern with URL-encoded content', () => {
        const result = buildWavePattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            waveHeight: 10,
            wavelength: 100,
        });

        expect(result).toContain('background-color: #ffffff;');
        expect(result).toContain('background-image: url("data:image/svg+xml,');
        expect(result).toContain('background-size: 100px 20px;');
        // The SVG's raw '#' and quote characters must be percent-encoded
        // for the data URI to be valid; the literal '#000000' must not
        // appear unescaped inside the url(...) value.
        expect(result).not.toContain("#000000'");
        expect(result).toContain('%23000000');
    });

    test('scales the SVG dimensions with waveHeight and wavelength', () => {
        const result = buildWavePattern({
            backgroundColor: '#fafafa',
            patternColor: '#111111',
            waveHeight: 15,
            wavelength: 60,
        });

        expect(result).toContain('background-size: 60px 30px;');
    });
});
