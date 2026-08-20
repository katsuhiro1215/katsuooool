export type ButtonSize = 'small' | 'medium' | 'large';

export interface ButtonInput {
    backgroundColor: string;
    textColor: string;
    size: ButtonSize;
    borderRadius: number;
    borderEnabled: boolean;
    borderColor: string;
}

export interface ButtonOutput {
    html: string;
    css: string;
}

const BUTTON_SIZE_STYLES: Record<
    ButtonSize,
    { padding: string; fontSize: string }
> = {
    small: { padding: '6px 12px', fontSize: '14px' },
    medium: { padding: '10px 20px', fontSize: '16px' },
    large: { padding: '14px 28px', fontSize: '18px' },
};

export function buildButton(input: ButtonInput): ButtonOutput {
    const { padding, fontSize } = BUTTON_SIZE_STYLES[input.size];
    const border = input.borderEnabled
        ? `border: 1px solid ${input.borderColor};`
        : 'border: none;';

    const css = [
        '.btn {',
        `    background-color: ${input.backgroundColor};`,
        `    color: ${input.textColor};`,
        `    padding: ${padding};`,
        `    font-size: ${fontSize};`,
        `    border-radius: ${input.borderRadius}px;`,
        `    ${border}`,
        '    cursor: pointer;',
        '}',
    ].join('\n');

    return {
        html: '<button class="btn">ボタン</button>',
        css,
    };
}

export type CardVariant = 'plain' | 'image';

export interface CardInput {
    variant: CardVariant;
    backgroundColor: string;
    borderRadius: number;
    shadowEnabled: boolean;
    padding: number;
    title: string;
    text: string;
}

export interface CardOutput {
    html: string;
    css: string;
}

const CARD_SHADOW = '0 1px 3px rgba(0, 0, 0, 0.1)';

export function buildCard(input: CardInput): CardOutput {
    const boxShadow = input.shadowEnabled ? CARD_SHADOW : 'none';

    if (input.variant === 'image') {
        const html = [
            '<div class="card">',
            '    <img class="card-image" src="https://placehold.co/400x200" alt="">',
            '    <div class="card-body">',
            `        <h3 class="card-title">${input.title}</h3>`,
            `        <p class="card-text">${input.text}</p>`,
            '    </div>',
            '</div>',
        ].join('\n');

        const css = [
            '.card {',
            `    background-color: ${input.backgroundColor};`,
            `    border-radius: ${input.borderRadius}px;`,
            `    box-shadow: ${boxShadow};`,
            '    overflow: hidden;',
            '}',
            '',
            '.card-image {',
            '    display: block;',
            '    width: 100%;',
            '    height: 160px;',
            '    object-fit: cover;',
            '}',
            '',
            '.card-body {',
            `    padding: ${input.padding}px;`,
            '}',
            '',
            '.card-title {',
            '    margin: 0 0 8px;',
            '    font-size: 18px;',
            '    font-weight: bold;',
            '}',
            '',
            '.card-text {',
            '    margin: 0;',
            '    font-size: 14px;',
            '    color: #666;',
            '}',
        ].join('\n');

        return { html, css };
    }

    const html = [
        '<div class="card">',
        `    <h3 class="card-title">${input.title}</h3>`,
        `    <p class="card-text">${input.text}</p>`,
        '</div>',
    ].join('\n');

    const css = [
        '.card {',
        `    background-color: ${input.backgroundColor};`,
        `    border-radius: ${input.borderRadius}px;`,
        `    box-shadow: ${boxShadow};`,
        `    padding: ${input.padding}px;`,
        '}',
        '',
        '.card-title {',
        '    margin: 0 0 8px;',
        '    font-size: 18px;',
        '    font-weight: bold;',
        '}',
        '',
        '.card-text {',
        '    margin: 0;',
        '    font-size: 14px;',
        '    color: #666;',
        '}',
    ].join('\n');

    return { html, css };
}

export interface PaginationInput {
    currentPage: number;
    totalPages: number;
    accentColor: string;
}

export interface PaginationOutput {
    html: string;
    css: string;
}

export function computePaginationPages(
    currentPage: number,
    totalPages: number,
): (number | 'ellipsis')[] {
    const pages = new Set<number>();
    pages.add(1);
    pages.add(totalPages);

    for (let page = currentPage - 1; page <= currentPage + 1; page++) {
        if (page >= 1 && page <= totalPages) {
            pages.add(page);
        }
    }

    const sorted = Array.from(pages).sort((a, b) => a - b);
    const result: (number | 'ellipsis')[] = [];
    let previous: number | null = null;

    for (const page of sorted) {
        if (previous !== null && page - previous > 1) {
            result.push('ellipsis');
        }

        result.push(page);
        previous = page;
    }

    return result;
}

export function buildPagination(input: PaginationInput): PaginationOutput {
    const pages = computePaginationPages(input.currentPage, input.totalPages);
    const isFirst = input.currentPage <= 1;
    const isLast = input.currentPage >= input.totalPages;

    const prevClass = isFirst
        ? 'pagination-prev is-disabled'
        : 'pagination-prev';
    const nextClass = isLast
        ? 'pagination-next is-disabled'
        : 'pagination-next';

    const pageLinks = pages
        .map((page) => {
            if (page === 'ellipsis') {
                return '    <span class="pagination-ellipsis">…</span>';
            }

            const activeClass =
                page === input.currentPage ? ' pagination-page-active' : '';

            return `    <a href="#" class="pagination-page${activeClass}">${page}</a>`;
        })
        .join('\n');

    const html = [
        '<nav class="pagination">',
        `    <a href="#" class="${prevClass}">前へ</a>`,
        pageLinks,
        `    <a href="#" class="${nextClass}">次へ</a>`,
        '</nav>',
    ].join('\n');

    const css = [
        '.pagination {',
        '    display: flex;',
        '    align-items: center;',
        '    gap: 4px;',
        '}',
        '',
        '.pagination-prev,',
        '.pagination-next,',
        '.pagination-page {',
        '    display: inline-block;',
        '    padding: 6px 12px;',
        '    border-radius: 4px;',
        '    color: #333;',
        '    text-decoration: none;',
        '    font-size: 14px;',
        '}',
        '',
        '.pagination-prev:hover,',
        '.pagination-next:hover,',
        '.pagination-page:hover {',
        '    background-color: #f0f0f0;',
        '}',
        '',
        '.pagination-page-active {',
        `    background-color: ${input.accentColor};`,
        '    color: #ffffff;',
        '}',
        '',
        '.pagination-ellipsis {',
        '    padding: 6px 4px;',
        '    color: #999;',
        '}',
        '',
        '.is-disabled {',
        '    pointer-events: none;',
        '    opacity: 0.4;',
        '}',
    ].join('\n');

    return { html, css };
}

export interface ModalInput {
    title: string;
    text: string;
    backgroundColor: string;
    borderRadius: number;
    overlayOpacity: number;
}

export interface ModalOutput {
    html: string;
    css: string;
    js: string;
}

export function buildModal(input: ModalInput): ModalOutput {
    const html = [
        '<button type="button" id="modal-trigger" class="modal-trigger">開く</button>',
        '',
        '<div id="modal-overlay" class="modal-overlay">',
        '    <div class="modal">',
        `        <h3 class="modal-title">${input.title}</h3>`,
        `        <p class="modal-text">${input.text}</p>`,
        '        <button type="button" id="modal-close" class="modal-close">閉じる</button>',
        '    </div>',
        '</div>',
    ].join('\n');

    const css = [
        '.modal-overlay {',
        '    display: none;',
        '    position: fixed;',
        '    inset: 0;',
        `    background-color: rgba(0, 0, 0, ${input.overlayOpacity});`,
        '    align-items: center;',
        '    justify-content: center;',
        '}',
        '',
        '.modal-overlay.is-open {',
        '    display: flex;',
        '}',
        '',
        '.modal {',
        `    background-color: ${input.backgroundColor};`,
        `    border-radius: ${input.borderRadius}px;`,
        '    padding: 24px;',
        '    max-width: 400px;',
        '    width: 90%;',
        '}',
        '',
        '.modal-title {',
        '    margin: 0 0 12px;',
        '    font-size: 18px;',
        '    font-weight: bold;',
        '}',
        '',
        '.modal-text {',
        '    margin: 0 0 16px;',
        '    font-size: 14px;',
        '    color: #666;',
        '}',
        '',
        '.modal-close {',
        '    padding: 8px 16px;',
        '    border: none;',
        '    border-radius: 4px;',
        '    background-color: #e5e5e5;',
        '    cursor: pointer;',
        '}',
    ].join('\n');

    const js = [
        "const modalTrigger = document.getElementById('modal-trigger');",
        "const modalOverlay = document.getElementById('modal-overlay');",
        "const modalClose = document.getElementById('modal-close');",
        '',
        "modalTrigger.addEventListener('click', () => {",
        "    modalOverlay.classList.add('is-open');",
        '});',
        '',
        "modalClose.addEventListener('click', () => {",
        "    modalOverlay.classList.remove('is-open');",
        '});',
    ].join('\n');

    return { html, css, js };
}
