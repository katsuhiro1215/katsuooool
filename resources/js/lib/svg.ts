/**
 * Lightweight, dependency-free SVG minifier. Strips the constructs that
 * bloat editor-exported SVGs (XML declaration, DOCTYPE, comments, Inkscape/
 * Sodipodi editor metadata, inter-tag whitespace) without attempting a full
 * SVGO-style optimization pass (path data re-serialization, unused
 * def-stripping, etc.).
 */
export function optimizeSvg(input: string): string {
    let output = input;

    // XML declaration, e.g. <?xml version="1.0" encoding="UTF-8"?>
    output = output.replace(/^\s*<\?xml[^>]*\?>\s*/, '');

    // DOCTYPE declaration
    output = output.replace(/<!DOCTYPE[^>]*>\s*/gi, '');

    // Comments
    output = output.replace(/<!--[\s\S]*?-->/g, '');

    // <metadata>...</metadata> blocks (Illustrator/Inkscape provenance data)
    output = output.replace(/<metadata[\s\S]*?<\/metadata>\s*/gi, '');

    // Inkscape/Sodipodi namespaced attributes, e.g. inkscape:version="1.0"
    output = output.replace(/\s(?:inkscape|sodipodi):[\w-]+="[^"]*"/g, '');

    // The xmlns:inkscape / xmlns:sodipodi namespace declarations themselves
    output = output.replace(/\s+xmlns:(?:inkscape|sodipodi)="[^"]*"/g, '');

    // Whitespace between tags (indentation, newlines from pretty-printed source)
    output = output.replace(/>\s+</g, '><');

    return output.trim();
}

export interface OptimizeSvgResult {
    output: string;
    originalBytes: number;
    optimizedBytes: number;
    reductionPercent: number;
}

export function optimizeSvgWithStats(input: string): OptimizeSvgResult {
    const output = optimizeSvg(input);
    const originalBytes = new TextEncoder().encode(input).length;
    const optimizedBytes = new TextEncoder().encode(output).length;
    const reductionPercent =
        originalBytes === 0
            ? 0
            : Math.round((1 - optimizedBytes / originalBytes) * 100);

    return { output, originalBytes, optimizedBytes, reductionPercent };
}
