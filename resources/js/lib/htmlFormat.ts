const VOID_ELEMENTS = new Set([
    'area',
    'base',
    'br',
    'col',
    'embed',
    'hr',
    'img',
    'input',
    'link',
    'meta',
    'source',
    'track',
    'wbr',
]);

const INDENT_UNIT = '  ';

/**
 * Pretty-prints HTML by round-tripping it through the browser's own HTML
 * parser (DOMParser), then walking the resulting tree. Malformed input is
 * never an error here — the parser silently repairs it (closes unclosed
 * tags, drops stray closing tags, etc.), same as the browser would when
 * rendering it, so the output is always "some" formatted HTML rather than
 * a validation failure.
 */
export function formatHtml(input: string): string {
    const doc = new DOMParser().parseFromString(input, 'text/html');
    const lines: string[] = [];

    formatNode(doc.body, 0, lines);

    return lines.join('\n');
}

function formatNode(node: Node, depth: number, lines: string[]): void {
    const indent = INDENT_UNIT.repeat(depth);

    for (const child of Array.from(node.childNodes)) {
        if (child.nodeType === Node.TEXT_NODE) {
            const text = child.textContent?.trim();

            if (text) {
                lines.push(`${indent}${text}`);
            }

            continue;
        }

        if (child.nodeType !== Node.ELEMENT_NODE) {
            continue;
        }

        const element = child as Element;
        const tagName = element.tagName.toLowerCase();
        const attrs = Array.from(element.attributes)
            .map((attr) => ` ${attr.name}="${attr.value}"`)
            .join('');

        if (VOID_ELEMENTS.has(tagName)) {
            lines.push(`${indent}<${tagName}${attrs}>`);
            continue;
        }

        if (!element.hasChildNodes()) {
            lines.push(`${indent}<${tagName}${attrs}></${tagName}>`);
            continue;
        }

        lines.push(`${indent}<${tagName}${attrs}>`);
        formatNode(element, depth + 1, lines);
        lines.push(`${indent}</${tagName}>`);
    }
}
