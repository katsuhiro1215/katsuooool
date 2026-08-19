/**
 * SVG minifier built on the browser's own XML parser rather than regexes.
 *
 * An earlier regex-based version was replaced after review found it could
 * silently corrupt files: a comment-stripping regex matched literal
 * "<!--" text inside a CDATA section (deleting everything up to the next
 * real "-->"), a DOCTYPE regex couldn't span an internal entity subset
 * (the classic Illustrator `ns_extend` export), and unconditional
 * inter-tag whitespace removal changed how <text>/<tspan> content renders.
 * Parsing as real XML makes all of these structural, not textual,
 * problems — a literal "<!--" inside CDATA is never mistaken for a
 * comment, an internal DOCTYPE subset is just part of the doctype node,
 * and whitespace can be judged per-element instead of blindly.
 */

// Elements where inter-node whitespace is part of the rendered output and
// must not be stripped.
const WHITESPACE_SIGNIFICANT_TAGS = new Set([
    'text',
    'tspan',
    'textpath',
    'style',
    'title',
    'desc',
]);

const EDITOR_NAMESPACE_PREFIXES = ['inkscape', 'sodipodi'];

export function optimizeSvg(input: string): string {
    const trimmed = input.trim();

    if (trimmed === '') {
        return trimmed;
    }

    const doc = new DOMParser().parseFromString(trimmed, 'image/svg+xml');
    const root = doc.documentElement;

    if (
        !root ||
        root.getElementsByTagName('parsererror').length > 0 ||
        root.nodeName === 'parsererror'
    ) {
        // Not well-formed XML — leave the input untouched rather than risk
        // emitting something worse than what the user pasted in.
        return trimmed;
    }

    stripNode(root);

    return new XMLSerializer().serializeToString(root);
}

function stripNode(element: Element): void {
    for (const attr of Array.from(element.attributes)) {
        if (isEditorAttribute(attr.name)) {
            element.removeAttribute(attr.name);
        }
    }

    const preserveWhitespace = WHITESPACE_SIGNIFICANT_TAGS.has(
        element.localName.toLowerCase(),
    );

    for (const child of Array.from(element.childNodes)) {
        if (child.nodeType === Node.COMMENT_NODE) {
            element.removeChild(child);
            continue;
        }

        if (child.nodeType === Node.ELEMENT_NODE) {
            const childElement = child as Element;

            if (childElement.localName.toLowerCase() === 'metadata') {
                element.removeChild(child);
                continue;
            }

            stripNode(childElement);
            continue;
        }

        if (
            child.nodeType === Node.TEXT_NODE &&
            !preserveWhitespace &&
            /^\s*$/.test(child.textContent ?? '')
        ) {
            element.removeChild(child);
        }
    }
}

function isEditorAttribute(name: string): boolean {
    return EDITOR_NAMESPACE_PREFIXES.some(
        (prefix) => name === `xmlns:${prefix}` || name.startsWith(`${prefix}:`),
    );
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
