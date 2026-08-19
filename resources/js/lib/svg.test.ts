import { describe, expect, test } from 'vitest';

import { optimizeSvg, optimizeSvgWithStats } from './svg';

describe('optimizeSvg', () => {
    test('removes the XML declaration', () => {
        const input = '<?xml version="1.0" encoding="UTF-8"?><svg></svg>';
        expect(optimizeSvg(input)).toBe('<svg></svg>');
    });

    test('removes the DOCTYPE declaration', () => {
        const input =
            '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg></svg>';
        expect(optimizeSvg(input)).toBe('<svg></svg>');
    });

    test('removes comments', () => {
        const input = '<svg><!-- Generator: Adobe Illustrator --><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('removes a metadata block', () => {
        const input =
            '<svg><metadata><rdf:RDF>...</rdf:RDF></metadata><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('removes inkscape and sodipodi namespaced attributes and their xmlns declarations', () => {
        const input =
            '<svg xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.0.dtd" inkscape:version="1.0" sodipodi:docname="test.svg"><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('collapses whitespace between tags from pretty-printed source', () => {
        const input = '<svg>\n  <path d="M0 0"/>\n  <circle r="5"/>\n</svg>';
        expect(optimizeSvg(input)).toBe(
            '<svg><path d="M0 0"/><circle r="5"/></svg>',
        );
    });

    test('preserves real SVG content (viewBox, path data, fill)', () => {
        const input =
            '<svg viewBox="0 0 24 24"><path d="M12 2L2 22h20z" fill="#3366ff"/></svg>';
        expect(optimizeSvg(input)).toBe(input);
    });

    test('handles a realistic Illustrator-exported SVG end to end', () => {
        const input = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" inkscape:version="1.0" viewBox="0 0 24 24">
  <!-- Generator: Adobe Illustrator 25.0.0, SVG Export Plug-In -->
  <metadata>
    <rdf:RDF><cc:Work/></rdf:RDF>
  </metadata>
  <path d="M12 2L2 22h20z"/>
</svg>`;

        expect(optimizeSvg(input)).toBe(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 22h20z"/></svg>',
        );
    });
});

describe('optimizeSvgWithStats', () => {
    test('reports a positive reduction percentage for a bloated input', () => {
        const input =
            '<?xml version="1.0" encoding="UTF-8"?>\n<svg>\n  <!-- comment --> \n  <path d="M0 0"/>\n</svg>';
        const result = optimizeSvgWithStats(input);

        expect(result.output).toBe('<svg><path d="M0 0"/></svg>');
        expect(result.originalBytes).toBeGreaterThan(result.optimizedBytes);
        expect(result.reductionPercent).toBeGreaterThan(0);
    });

    test('reports zero reduction for an already-minimal input', () => {
        const input = '<svg><path d="M0 0"/></svg>';
        const result = optimizeSvgWithStats(input);

        expect(result.output).toBe(input);
        expect(result.reductionPercent).toBe(0);
    });

    test('does not divide by zero for an empty input', () => {
        const result = optimizeSvgWithStats('');
        expect(result.reductionPercent).toBe(0);
    });
});
