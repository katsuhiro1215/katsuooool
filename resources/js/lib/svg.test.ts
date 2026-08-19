// @vitest-environment jsdom
import { describe, expect, test } from 'vitest';

import { optimizeSvg, optimizeSvgWithStats } from './svg';

describe('optimizeSvg', () => {
    test('removes the XML declaration and DOCTYPE (by only serializing the root element)', () => {
        const input =
            '<?xml version="1.0" encoding="UTF-8"?><svg><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('removes a plain DOCTYPE declaration with an external DTD reference', () => {
        const input =
            '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('removes comments', () => {
        const input = '<svg><!-- Generator: Adobe Illustrator --><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('removes a metadata block', () => {
        const input =
            '<svg><metadata><rdf:RDF xmlns:rdf="http://x"><cc:Work xmlns:cc="http://y"/></rdf:RDF></metadata><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('removes inkscape and sodipodi namespaced attributes and their xmlns declarations, regardless of quote style', () => {
        const input =
            '<svg xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.0.dtd" inkscape:version="1.0" sodipodi:docname="test.svg"><path/></svg>';
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('strips editor attributes written with single quotes, not just double quotes', () => {
        // Regression test: the earlier regex-based implementation only
        // matched double-quoted attribute values and silently left
        // single-quoted inkscape:/sodipodi: attributes untouched.
        const input =
            "<svg xmlns:inkscape='http://www.inkscape.org/namespaces/inkscape' inkscape:version='1.0'><path/></svg>";
        expect(optimizeSvg(input)).toBe('<svg><path/></svg>');
    });

    test('collapses whitespace-only text nodes between tags from pretty-printed source', () => {
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

    test('does not treat a literal "<!--" inside a CDATA section as the start of a real comment', () => {
        // Regression test: the earlier regex-based comment stripper matched
        // across CDATA boundaries, deleting real markup between a literal
        // "<!--" inside CSS content and the next genuine "-->".
        const input =
            '<svg><style><![CDATA[ .a{content:"<!--"} ]]></style><path d="M0 0"/><!--x--></svg>';
        const output = optimizeSvg(input);

        expect(output).toContain('<path d="M0 0"/>');
        expect(output).toContain('CDATA');
        expect(output).not.toContain('<!--x-->');
    });

    test('handles a DOCTYPE with an internal entity subset (Illustrator ns_extend export)', () => {
        // Regression test: the earlier regex `<!DOCTYPE[^>]*>` couldn't
        // span the `>` inside `[<!ENTITY ns_extend "...">]`, leaving a
        // stray "]>" prefix in the output.
        const input =
            '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "x.dtd" [<!ENTITY ns_extend "http://ns.adobe.com">]><svg><path/></svg>';
        const output = optimizeSvg(input);

        expect(output).toBe('<svg><path/></svg>');
        expect(output).not.toContain(']>');
    });

    test('preserves whitespace-significant text inside <text>/<tspan> (does not merge words)', () => {
        // Regression test: unconditional inter-tag whitespace stripping
        // merged "a" and "b" into "ab" when they were separate <tspan>
        // elements separated by a newline, changing what the SVG renders.
        const input =
            '<svg><text><tspan>a</tspan>\n<tspan>b</tspan></text></svg>';
        const output = optimizeSvg(input);

        expect(output).toContain('<tspan>a</tspan>');
        expect(output).toContain('<tspan>b</tspan>');
        // A whitespace text node must still separate the two tspans.
        expect(output).toMatch(/<tspan>a<\/tspan>\s+<tspan>b<\/tspan>/);
    });

    test('falls back to the original input, unchanged, when the input is not well-formed XML', () => {
        const input = '<svg><path d="M0 0"</svg>'; // missing '>' on <path>
        expect(optimizeSvg(input)).toBe(input);
    });

    test('handles a realistic Illustrator-exported SVG end to end', () => {
        const input = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" inkscape:version="1.0" viewBox="0 0 24 24">
  <!-- Generator: Adobe Illustrator 25.0.0, SVG Export Plug-In -->
  <metadata>
    <rdf:RDF xmlns:rdf="http://x"><cc:Work xmlns:cc="http://y"/></rdf:RDF>
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
