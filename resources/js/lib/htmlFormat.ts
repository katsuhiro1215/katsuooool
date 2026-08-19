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

// Elements whose text content must be re-serialized verbatim, without
// re-indenting or trimming: <pre>/<textarea> because internal whitespace is
// significant to what they display, <script>/<style> because their content
// is raw JS/CSS, not HTML text that needs entity-escaping.
const RAW_TEXT_ELEMENTS = new Set(['pre', 'textarea', 'script', 'style']);

const INDENT_UNIT = '  ';

/**
 * Pretty-prints HTML by round-tripping it through the browser's own HTML
 * parser (DOMParser), then walking the resulting tree. Malformed input is
 * never an error here — the parser silently repairs it (closes unclosed
 * tags, drops stray closing tags, etc.), same as the browser would when
 * rendering it, so the output is always "some" formatted HTML rather than
 * a validation failure.
 *
 * A full document (containing <html>, <head>, or a doctype) is formatted
 * from the document root so nothing in <head> is silently dropped; a
 * fragment is formatted from <body> alone.
 */
export function formatHtml(input: string): string {
    const doc = new DOMParser().parseFromString(input, 'text/html');
    // Beyond the obvious explicit markers, the HTML parser itself can
    // promote elements into <head> that never had an explicit <head> tag
    // in the source — e.g. a bare "<script>...</script>" or
    // "<style>...</style>" fragment is placed in <head> per the HTML
    // parsing spec's early insertion modes. If that happened, treating
    // this as a body-only fragment would silently drop it.
    const isFullDocument =
        /<\s*(html|head)[\s>]|<!doctype/i.test(input) ||
        doc.head.hasChildNodes();
    const lines: string[] = [];

    if (isFullDocument) {
        if (doc.doctype) {
            lines.push('<!DOCTYPE html>');
        }

        formatElement(doc.documentElement, 0, lines);
    } else {
        formatNode(doc.body, 0, lines);
    }

    return lines.join('\n');
}

function formatNode(node: Node, depth: number, lines: string[]): void {
    for (const child of Array.from(node.childNodes)) {
        if (child.nodeType === Node.TEXT_NODE) {
            const text = child.textContent?.trim();

            if (text) {
                lines.push(`${INDENT_UNIT.repeat(depth)}${escapeText(text)}`);
            }

            continue;
        }

        if (child.nodeType === Node.ELEMENT_NODE) {
            formatElement(child as Element, depth, lines);
        }
    }
}

function formatElement(element: Element, depth: number, lines: string[]): void {
    const indent = INDENT_UNIT.repeat(depth);
    const tagName = element.tagName.toLowerCase();
    const attrs = Array.from(element.attributes)
        .map((attr) => ` ${attr.name}="${escapeAttributeValue(attr.value)}"`)
        .join('');

    if (VOID_ELEMENTS.has(tagName)) {
        lines.push(`${indent}<${tagName}${attrs}>`);

        return;
    }

    if (RAW_TEXT_ELEMENTS.has(tagName)) {
        // Whitespace and entity-decoding here must stay exactly as the
        // source had it: reformatting a <pre>/<textarea>'s content changes
        // what it displays, and <script>/<style> content is not HTML text
        // (escaping it would corrupt the JS/CSS).
        lines.push(
            `${indent}<${tagName}${attrs}>${element.textContent ?? ''}</${tagName}>`,
        );

        return;
    }

    if (!element.hasChildNodes()) {
        lines.push(`${indent}<${tagName}${attrs}></${tagName}>`);

        return;
    }

    lines.push(`${indent}<${tagName}${attrs}>`);
    formatNode(element, depth + 1, lines);
    lines.push(`${indent}</${tagName}>`);
}

function escapeText(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function escapeAttributeValue(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}
