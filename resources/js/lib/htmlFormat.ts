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

// <script>/<style> content is not HTML text — it's raw JS/CSS that the
// parser never entity-decodes, so it must come back out unescaped and
// unmodified.
const SCRIPT_OR_STYLE = new Set(['script', 'style']);

// <pre>/<textarea> content IS ordinary (entity-decoded) HTML text and does
// need re-escaping, but its whitespace is significant to what it displays,
// so it can't be trimmed or re-indented like everything else. <pre> can
// still contain child elements (a fenced code block wrapping a <code>
// element is the common case); <textarea> is parsed as raw text with no
// child elements at all, per the HTML spec's RCDATA rules for it.
const WHITESPACE_PRESERVING_ELEMENTS = new Set(['pre', 'textarea']);

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

    if (SCRIPT_OR_STYLE.has(tagName)) {
        lines.push(
            `${indent}<${tagName}${attrs}>${element.textContent ?? ''}</${tagName}>`,
        );

        return;
    }

    if (WHITESPACE_PRESERVING_ELEMENTS.has(tagName)) {
        lines.push(
            `${indent}<${tagName}${attrs}>${serializeVerbatim(element)}</${tagName}>`,
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

/**
 * Serializes a node's children inline, with no indentation or trimming, for
 * use inside whitespace-preserving elements. Text is still escaped and
 * nested elements are still fully serialized (including their own
 * <script>/<style> children unescaped) — only the "one node per line, with
 * indentation" formatting is skipped.
 */
function serializeVerbatim(node: Node): string {
    let output = '';

    for (const child of Array.from(node.childNodes)) {
        if (child.nodeType === Node.TEXT_NODE) {
            output += escapeText(child.textContent ?? '');
            continue;
        }

        if (child.nodeType !== Node.ELEMENT_NODE) {
            continue;
        }

        const element = child as Element;
        const tagName = element.tagName.toLowerCase();
        const attrs = Array.from(element.attributes)
            .map(
                (attr) => ` ${attr.name}="${escapeAttributeValue(attr.value)}"`,
            )
            .join('');

        if (VOID_ELEMENTS.has(tagName)) {
            output += `<${tagName}${attrs}>`;
        } else if (SCRIPT_OR_STYLE.has(tagName)) {
            output += `<${tagName}${attrs}>${element.textContent ?? ''}</${tagName}>`;
        } else {
            output += `<${tagName}${attrs}>${serializeVerbatim(element)}</${tagName}>`;
        }
    }

    return output;
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
