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

export type ToastVariant = 'success' | 'error' | 'info' | 'warning';

export interface ToastInput {
    variant: ToastVariant;
    message: string;
    duration: number;
}

export interface ToastOutput {
    html: string;
    css: string;
    js: string;
}

const TOAST_COLORS: Record<ToastVariant, string> = {
    success: '#16a34a',
    error: '#dc2626',
    info: '#2563eb',
    warning: '#d97706',
};

export function buildToast(input: ToastInput): ToastOutput {
    const html = [
        '<button type="button" id="toast-trigger" class="toast-trigger">表示する</button>',
        '',
        `<div id="toast" class="toast toast-${input.variant}">`,
        `    <span class="toast-message">${input.message}</span>`,
        '    <button type="button" id="toast-close" class="toast-close">×</button>',
        '</div>',
    ].join('\n');

    const css = [
        '.toast {',
        '    display: none;',
        '    position: fixed;',
        '    right: 16px;',
        '    bottom: 16px;',
        '    align-items: center;',
        '    gap: 12px;',
        '    padding: 12px 16px;',
        '    border-radius: 6px;',
        '    color: #ffffff;',
        '    font-size: 14px;',
        '}',
        '',
        '.toast.is-open {',
        '    display: flex;',
        '}',
        '',
        '.toast-success {',
        `    background-color: ${TOAST_COLORS.success};`,
        '}',
        '',
        '.toast-error {',
        `    background-color: ${TOAST_COLORS.error};`,
        '}',
        '',
        '.toast-info {',
        `    background-color: ${TOAST_COLORS.info};`,
        '}',
        '',
        '.toast-warning {',
        `    background-color: ${TOAST_COLORS.warning};`,
        '}',
        '',
        '.toast-close {',
        '    background: none;',
        '    border: none;',
        '    color: inherit;',
        '    font-size: 16px;',
        '    cursor: pointer;',
        '}',
    ].join('\n');

    const js = [
        "const toastTrigger = document.getElementById('toast-trigger');",
        "const toast = document.getElementById('toast');",
        "const toastClose = document.getElementById('toast-close');",
        '',
        "toastTrigger.addEventListener('click', () => {",
        "    toast.classList.add('is-open');",
        '    setTimeout(() => {',
        "        toast.classList.remove('is-open');",
        `    }, ${input.duration * 1000});`,
        '});',
        '',
        "toastClose.addEventListener('click', () => {",
        "    toast.classList.remove('is-open');",
        '});',
    ].join('\n');

    return { html, css, js };
}

export type LoadingType = 'spinner' | 'dots' | 'bar';

export interface LoadingInput {
    type: LoadingType;
    color: string;
    size: number;
}

export interface LoadingOutput {
    html: string;
    css: string;
}

export function buildLoading(input: LoadingInput): LoadingOutput {
    if (input.type === 'dots') {
        const dotSize = Math.round(input.size / 4);

        const html = [
            '<div class="loading-dots">',
            '    <span></span>',
            '    <span></span>',
            '    <span></span>',
            '</div>',
        ].join('\n');

        const css = [
            '.loading-dots {',
            '    display: flex;',
            '    gap: 6px;',
            '}',
            '',
            '.loading-dots span {',
            `    width: ${dotSize}px;`,
            `    height: ${dotSize}px;`,
            '    border-radius: 50%;',
            `    background-color: ${input.color};`,
            '    animation: loading-bounce 0.6s ease-in-out infinite;',
            '}',
            '',
            '.loading-dots span:nth-child(2) {',
            '    animation-delay: 0.15s;',
            '}',
            '',
            '.loading-dots span:nth-child(3) {',
            '    animation-delay: 0.3s;',
            '}',
            '',
            '@keyframes loading-bounce {',
            '    0%, 80%, 100% {',
            '        transform: scale(0.6);',
            '        opacity: 0.5;',
            '    }',
            '    40% {',
            '        transform: scale(1);',
            '        opacity: 1;',
            '    }',
            '}',
        ].join('\n');

        return { html, css };
    }

    if (input.type === 'bar') {
        const html = [
            '<div class="loading-bar">',
            '    <div class="loading-bar-fill"></div>',
            '</div>',
        ].join('\n');

        const css = [
            '.loading-bar {',
            `    width: ${input.size * 4}px;`,
            '    height: 6px;',
            '    background-color: #e5e5e5;',
            '    border-radius: 3px;',
            '    overflow: hidden;',
            '}',
            '',
            '.loading-bar-fill {',
            '    width: 40%;',
            '    height: 100%;',
            `    background-color: ${input.color};`,
            '    border-radius: 3px;',
            '    animation: loading-bar-move 1.2s ease-in-out infinite;',
            '}',
            '',
            '@keyframes loading-bar-move {',
            '    0% {',
            '        transform: translateX(-100%);',
            '    }',
            '    100% {',
            '        transform: translateX(350%);',
            '    }',
            '}',
        ].join('\n');

        return { html, css };
    }

    const borderWidth = Math.max(2, Math.round(input.size / 10));

    const css = [
        '.loading-spinner {',
        `    width: ${input.size}px;`,
        `    height: ${input.size}px;`,
        `    border: ${borderWidth}px solid #e5e5e5;`,
        `    border-top-color: ${input.color};`,
        '    border-radius: 50%;',
        '    animation: loading-spin 0.8s linear infinite;',
        '}',
        '',
        '@keyframes loading-spin {',
        '    to {',
        '        transform: rotate(360deg);',
        '    }',
        '}',
    ].join('\n');

    return { html: '<div class="loading-spinner"></div>', css };
}

export type SkeletonType = 'text' | 'avatar' | 'card';

export interface SkeletonInput {
    type: SkeletonType;
    lines: number;
    color: string;
}

export interface SkeletonOutput {
    html: string;
    css: string;
}

function buildSkeletonTextLines(lines: number, indent: string): string[] {
    const count = Math.max(1, lines);
    const result: string[] = [];

    for (let i = 0; i < count; i += 1) {
        const isLast = i === count - 1;
        const modifier = isLast && count > 1 ? ' skeleton-line-short' : '';
        result.push(
            `${indent}<div class="skeleton skeleton-line${modifier}"></div>`,
        );
    }

    return result;
}

export function buildSkeleton(input: SkeletonInput): SkeletonOutput {
    let html: string;

    if (input.type === 'avatar') {
        html = [
            '<div class="skeleton-avatar-row">',
            '    <div class="skeleton skeleton-circle"></div>',
            '    <div class="skeleton-text">',
            ...buildSkeletonTextLines(input.lines, '        '),
            '    </div>',
            '</div>',
        ].join('\n');
    } else if (input.type === 'card') {
        html = [
            '<div class="skeleton-card">',
            '    <div class="skeleton skeleton-image"></div>',
            '    <div class="skeleton-text">',
            ...buildSkeletonTextLines(input.lines, '        '),
            '    </div>',
            '</div>',
        ].join('\n');
    } else {
        html = [
            '<div class="skeleton-text">',
            ...buildSkeletonTextLines(input.lines, '    '),
            '</div>',
        ].join('\n');
    }

    const cssParts = [
        '.skeleton {',
        `    background-color: ${input.color};`,
        '    background-image: linear-gradient(',
        '        90deg,',
        '        transparent 0%,',
        '        rgba(255, 255, 255, 0.5) 50%,',
        '        transparent 100%',
        '    );',
        '    background-size: 200% 100%;',
        '    animation: skeleton-shimmer 1.5s ease-in-out infinite;',
        '}',
        '',
        '.skeleton-text {',
        '    display: flex;',
        '    flex-direction: column;',
        '    gap: 8px;',
        '}',
        '',
        '.skeleton-line {',
        '    height: 12px;',
        '    border-radius: 4px;',
        '}',
        '',
        '.skeleton-line-short {',
        '    width: 60%;',
        '}',
    ];

    if (input.type === 'avatar') {
        cssParts.push(
            '',
            '.skeleton-avatar-row {',
            '    display: flex;',
            '    align-items: center;',
            '    gap: 12px;',
            '}',
            '',
            '.skeleton-circle {',
            '    width: 48px;',
            '    height: 48px;',
            '    border-radius: 50%;',
            '    flex-shrink: 0;',
            '}',
        );
    } else if (input.type === 'card') {
        cssParts.push(
            '',
            '.skeleton-card {',
            '    display: flex;',
            '    flex-direction: column;',
            '    gap: 12px;',
            '}',
            '',
            '.skeleton-image {',
            '    width: 100%;',
            '    height: 160px;',
            '    border-radius: 8px;',
            '}',
        );
    }

    cssParts.push(
        '',
        '@keyframes skeleton-shimmer {',
        '    0% {',
        '        background-position: 200% 0;',
        '    }',
        '    100% {',
        '        background-position: -200% 0;',
        '    }',
        '}',
    );

    return { html, css: cssParts.join('\n') };
}

export type FormInputType = 'text' | 'email' | 'password' | 'number';

export interface FormInputInput {
    type: FormInputType;
    placeholder: string;
    borderRadius: number;
    borderColor: string;
    disabled: boolean;
}

export interface FormInputOutput {
    html: string;
    css: string;
}

export function buildFormInput(input: FormInputInput): FormInputOutput {
    const disabledAttr = input.disabled ? ' disabled' : '';
    const html = `<input class="form-input" type="${input.type}" placeholder="${input.placeholder}"${disabledAttr}>`;

    const css = [
        '.form-input {',
        '    display: block;',
        '    width: 100%;',
        '    padding: 8px 12px;',
        '    font-size: 14px;',
        `    border: 1px solid ${input.borderColor};`,
        `    border-radius: ${input.borderRadius}px;`,
        '    box-sizing: border-box;',
        '}',
        '',
        '.form-input:focus {',
        '    outline: none;',
        `    border-color: ${input.borderColor};`,
        '    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);',
        '}',
        '',
        '.form-input:disabled {',
        '    background-color: #f5f5f5;',
        '    color: #999;',
        '    cursor: not-allowed;',
        '}',
    ].join('\n');

    return { html, css };
}
