import { describe, expect, test } from 'vitest';

import {
    buildButton,
    buildCard,
    buildFooter,
    buildFormCheck,
    buildFormGroup,
    buildFormInput,
    buildFormSelect,
    buildFormTextarea,
    buildHeader,
    buildLoading,
    buildModal,
    buildPagination,
    buildSkeleton,
    buildToast,
    computePaginationPages,
} from './uiPatternGenerators';

describe('buildButton', () => {
    test('builds the HTML snippet with a fixed class name', () => {
        const result = buildButton({
            backgroundColor: '#2563eb',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 6,
            borderEnabled: false,
            borderColor: '#000000',
        });

        expect(result.html).toBe('<button class="btn">ボタン</button>');
    });

    test('builds the CSS rule with border: none when borderEnabled is false', () => {
        const result = buildButton({
            backgroundColor: '#2563eb',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 6,
            borderEnabled: false,
            borderColor: '#000000',
        });

        expect(result.css).toBe(
            '.btn {\n' +
                '    background-color: #2563eb;\n' +
                '    color: #ffffff;\n' +
                '    padding: 10px 20px;\n' +
                '    font-size: 16px;\n' +
                '    border-radius: 6px;\n' +
                '    border: none;\n' +
                '    cursor: pointer;\n' +
                '}',
        );
    });

    test('builds the CSS rule with a solid border when borderEnabled is true', () => {
        const result = buildButton({
            backgroundColor: '#2563eb',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 6,
            borderEnabled: true,
            borderColor: '#111111',
        });

        expect(result.css).toContain('border: 1px solid #111111;');
    });

    test.each([
        ['small', '6px 12px', '14px'],
        ['medium', '10px 20px', '16px'],
        ['large', '14px 28px', '18px'],
    ] as const)(
        'maps size "%s" to padding "%s" and font-size "%s"',
        (size, padding, fontSize) => {
            const result = buildButton({
                backgroundColor: '#000000',
                textColor: '#ffffff',
                size,
                borderRadius: 0,
                borderEnabled: false,
                borderColor: '#000000',
            });

            expect(result.css).toContain(`padding: ${padding};`);
            expect(result.css).toContain(`font-size: ${fontSize};`);
        },
    );

    test('reflects the border-radius value', () => {
        const result = buildButton({
            backgroundColor: '#000000',
            textColor: '#ffffff',
            size: 'medium',
            borderRadius: 24,
            borderEnabled: false,
            borderColor: '#000000',
        });

        expect(result.css).toContain('border-radius: 24px;');
    });
});

describe('buildCard', () => {
    test('builds a plain card with title, text, and no image markup', () => {
        const result = buildCard({
            variant: 'plain',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            shadowEnabled: true,
            padding: 16,
            title: 'タイトル',
            text: '本文テキストがここに入ります。',
        });

        expect(result.html).toBe(
            '<div class="card">\n' +
                '    <h3 class="card-title">タイトル</h3>\n' +
                '    <p class="card-text">本文テキストがここに入ります。</p>\n' +
                '</div>',
        );
        expect(result.html).not.toContain('<img');
        expect(result.css).toContain('padding: 16px;');
    });

    test('builds a plain card with box-shadow: none when shadowEnabled is false', () => {
        const result = buildCard({
            variant: 'plain',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            shadowEnabled: false,
            padding: 16,
            title: 'タイトル',
            text: '本文',
        });

        expect(result.css).toContain('box-shadow: none;');
    });

    test('builds an image card with a placeholder <img> and a .card-body wrapper', () => {
        const result = buildCard({
            variant: 'image',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            shadowEnabled: true,
            padding: 16,
            title: 'タイトル',
            text: '本文',
        });

        expect(result.html).toContain(
            '<img class="card-image" src="https://placehold.co/400x200" alt="">',
        );
        expect(result.html).toContain('<div class="card-body">');
        expect(result.css).toContain('.card-image {');
        expect(result.css).toContain('.card-body {');
    });

    test('reflects the border-radius and shadow value for the image variant', () => {
        const result = buildCard({
            variant: 'image',
            backgroundColor: '#f5f5f5',
            borderRadius: 12,
            shadowEnabled: true,
            padding: 20,
            title: 'タイトル',
            text: '本文',
        });

        expect(result.css).toContain('border-radius: 12px;');
        expect(result.css).toContain(
            'box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);',
        );
        expect(result.css).toContain('padding: 20px;');
    });
});

describe('computePaginationPages', () => {
    test('returns just [1] when there is only one page', () => {
        expect(computePaginationPages(1, 1)).toEqual([1]);
    });

    test('shows all pages with no ellipsis when the total is small', () => {
        expect(computePaginationPages(2, 3)).toEqual([1, 2, 3]);
    });

    test('inserts an ellipsis after page 2 when near the start', () => {
        expect(computePaginationPages(1, 5)).toEqual([1, 2, 'ellipsis', 5]);
    });

    test('inserts ellipses on both sides when in the middle', () => {
        expect(computePaginationPages(5, 10)).toEqual([
            1,
            'ellipsis',
            4,
            5,
            6,
            'ellipsis',
            10,
        ]);
    });

    test('inserts an ellipsis before the last pages when near the end', () => {
        expect(computePaginationPages(10, 10)).toEqual([1, 'ellipsis', 9, 10]);
    });
});

describe('buildPagination', () => {
    test('marks the current page as active and disables nothing in the middle', () => {
        const result = buildPagination({
            currentPage: 5,
            totalPages: 10,
            accentColor: '#2563eb',
        });

        expect(result.html).toContain(
            '<a href="#" class="pagination-page pagination-page-active">5</a>',
        );
        expect(result.html).toContain(
            '<span class="pagination-ellipsis">…</span>',
        );
        expect(result.html).not.toContain('is-disabled');
    });

    test('disables the "prev" link on the first page', () => {
        const result = buildPagination({
            currentPage: 1,
            totalPages: 10,
            accentColor: '#2563eb',
        });

        expect(result.html).toContain(
            '<a href="#" class="pagination-prev is-disabled">前へ</a>',
        );
        expect(result.html).not.toContain('pagination-next is-disabled');
    });

    test('disables the "next" link on the last page', () => {
        const result = buildPagination({
            currentPage: 10,
            totalPages: 10,
            accentColor: '#2563eb',
        });

        expect(result.html).toContain(
            '<a href="#" class="pagination-next is-disabled">次へ</a>',
        );
        expect(result.html).not.toContain('pagination-prev is-disabled');
    });

    test('reflects the accent color on the active page in the CSS', () => {
        const result = buildPagination({
            currentPage: 3,
            totalPages: 5,
            accentColor: '#16a34a',
        });

        expect(result.css).toContain('background-color: #16a34a;');
    });
});

describe('buildModal', () => {
    test('builds HTML with a trigger button, title, text, and a close button', () => {
        const result = buildModal({
            title: 'タイトル',
            text: '本文テキストがここに入ります。',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            overlayOpacity: 0.5,
        });

        expect(result.html).toContain(
            '<button type="button" id="modal-trigger" class="modal-trigger">開く</button>',
        );
        expect(result.html).toContain('<h3 class="modal-title">タイトル</h3>');
        expect(result.html).toContain(
            '<p class="modal-text">本文テキストがここに入ります。</p>',
        );
        expect(result.html).toContain(
            '<button type="button" id="modal-close" class="modal-close">閉じる</button>',
        );
    });

    test('reflects background color, border-radius, and overlay opacity in the CSS', () => {
        const result = buildModal({
            title: 'タイトル',
            text: '本文',
            backgroundColor: '#f5f5f5',
            borderRadius: 12,
            overlayOpacity: 0.7,
        });

        expect(result.css).toContain('background-color: #f5f5f5;');
        expect(result.css).toContain('border-radius: 12px;');
        expect(result.css).toContain('background-color: rgba(0, 0, 0, 0.7);');
    });

    test('builds JS that toggles the "is-open" class on trigger/close clicks', () => {
        const result = buildModal({
            title: 'タイトル',
            text: '本文',
            backgroundColor: '#ffffff',
            borderRadius: 8,
            overlayOpacity: 0.5,
        });

        expect(result.js).toContain("getElementById('modal-trigger')");
        expect(result.js).toContain("getElementById('modal-overlay')");
        expect(result.js).toContain("getElementById('modal-close')");
        expect(result.js).toContain("classList.add('is-open')");
        expect(result.js).toContain("classList.remove('is-open')");
    });
});

describe('buildToast', () => {
    test('builds HTML with a trigger, the message, a close button, and the variant class', () => {
        const result = buildToast({
            variant: 'success',
            message: '保存しました。',
            duration: 3,
        });

        expect(result.html).toContain(
            '<button type="button" id="toast-trigger" class="toast-trigger">表示する</button>',
        );
        expect(result.html).toContain(
            '<div id="toast" class="toast toast-success">',
        );
        expect(result.html).toContain(
            '<span class="toast-message">保存しました。</span>',
        );
        expect(result.html).toContain(
            '<button type="button" id="toast-close" class="toast-close">×</button>',
        );
    });

    test.each([
        ['success', '#16a34a'],
        ['error', '#dc2626'],
        ['info', '#2563eb'],
        ['warning', '#d97706'],
    ] as const)(
        'includes a .toast-%s rule with background-color %s in the CSS',
        (variant, color) => {
            const result = buildToast({
                variant,
                message: 'テスト',
                duration: 3,
            });

            expect(result.css).toContain(`.toast-${variant} {`);
            expect(result.css).toContain(`background-color: ${color};`);
        },
    );

    test('reflects the duration (in ms) in the auto-dismiss setTimeout', () => {
        const result = buildToast({
            variant: 'info',
            message: 'テスト',
            duration: 5,
        });

        expect(result.js).toContain('}, 5000);');
    });

    test('builds JS that shows on trigger click and dismisses on close click', () => {
        const result = buildToast({
            variant: 'info',
            message: 'テスト',
            duration: 3,
        });

        expect(result.js).toContain("getElementById('toast-trigger')");
        expect(result.js).toContain("getElementById('toast')");
        expect(result.js).toContain("getElementById('toast-close')");
        expect(result.js).toContain("classList.add('is-open')");
        expect(result.js).toContain("classList.remove('is-open')");
    });
});

describe('buildLoading', () => {
    test('builds a spinner with size, color, and a rotate keyframe', () => {
        const result = buildLoading({
            type: 'spinner',
            color: '#2563eb',
            size: 40,
        });

        expect(result.html).toBe('<div class="loading-spinner"></div>');
        expect(result.css).toContain('width: 40px;');
        expect(result.css).toContain('height: 40px;');
        expect(result.css).toContain('border-top-color: #2563eb;');
        expect(result.css).toContain('@keyframes loading-spin {');
        expect(result.css).toContain('transform: rotate(360deg);');
    });

    test('scales the spinner border width with size', () => {
        const small = buildLoading({
            type: 'spinner',
            color: '#000000',
            size: 20,
        });
        const large = buildLoading({
            type: 'spinner',
            color: '#000000',
            size: 80,
        });

        expect(small.css).toContain('border: 2px solid #e5e5e5;');
        expect(large.css).toContain('border: 8px solid #e5e5e5;');
    });

    test('builds three staggered dots', () => {
        const result = buildLoading({
            type: 'dots',
            color: '#16a34a',
            size: 40,
        });

        expect(result.html).toBe(
            '<div class="loading-dots">\n' +
                '    <span></span>\n' +
                '    <span></span>\n' +
                '    <span></span>\n' +
                '</div>',
        );
        expect(result.css).toContain('background-color: #16a34a;');
        expect(result.css).toContain('.loading-dots span:nth-child(2) {');
        expect(result.css).toContain('animation-delay: 0.15s;');
        expect(result.css).toContain('.loading-dots span:nth-child(3) {');
        expect(result.css).toContain('animation-delay: 0.3s;');
        expect(result.css).toContain('@keyframes loading-bounce {');
    });

    test('builds an indeterminate bar whose width scales with size', () => {
        const result = buildLoading({
            type: 'bar',
            color: '#dc2626',
            size: 40,
        });

        expect(result.html).toBe(
            '<div class="loading-bar">\n' +
                '    <div class="loading-bar-fill"></div>\n' +
                '</div>',
        );
        expect(result.css).toContain('width: 160px;');
        expect(result.css).toContain('background-color: #dc2626;');
        expect(result.css).toContain('@keyframes loading-bar-move {');
    });
});

describe('buildSkeleton', () => {
    test('builds stacked text lines with the last line shortened', () => {
        const result = buildSkeleton({
            type: 'text',
            lines: 3,
            color: '#e5e5e5',
        });

        expect(result.html).toBe(
            '<div class="skeleton-text">\n' +
                '    <div class="skeleton skeleton-line"></div>\n' +
                '    <div class="skeleton skeleton-line"></div>\n' +
                '    <div class="skeleton skeleton-line skeleton-line-short"></div>\n' +
                '</div>',
        );
        expect(result.css).toContain('background-color: #e5e5e5;');
        expect(result.css).toContain('@keyframes skeleton-shimmer {');
    });

    test('does not shorten the line when only one line is requested', () => {
        const result = buildSkeleton({
            type: 'text',
            lines: 1,
            color: '#e5e5e5',
        });

        expect(result.html).toBe(
            '<div class="skeleton-text">\n' +
                '    <div class="skeleton skeleton-line"></div>\n' +
                '</div>',
        );
    });

    test('builds an avatar row with a circle and text lines', () => {
        const result = buildSkeleton({
            type: 'avatar',
            lines: 2,
            color: '#e5e5e5',
        });

        expect(result.html).toBe(
            '<div class="skeleton-avatar-row">\n' +
                '    <div class="skeleton skeleton-circle"></div>\n' +
                '    <div class="skeleton-text">\n' +
                '        <div class="skeleton skeleton-line"></div>\n' +
                '        <div class="skeleton skeleton-line skeleton-line-short"></div>\n' +
                '    </div>\n' +
                '</div>',
        );
        expect(result.css).toContain('.skeleton-circle {');
        expect(result.css).toContain('.skeleton-avatar-row {');
    });

    test('builds a card with an image block and text lines', () => {
        const result = buildSkeleton({
            type: 'card',
            lines: 2,
            color: '#e5e5e5',
        });

        expect(result.html).toBe(
            '<div class="skeleton-card">\n' +
                '    <div class="skeleton skeleton-image"></div>\n' +
                '    <div class="skeleton-text">\n' +
                '        <div class="skeleton skeleton-line"></div>\n' +
                '        <div class="skeleton skeleton-line skeleton-line-short"></div>\n' +
                '    </div>\n' +
                '</div>',
        );
        expect(result.css).toContain('.skeleton-image {');
        expect(result.css).toContain('.skeleton-card {');
    });
});

describe('buildFormInput', () => {
    test('builds an input tag with the given type and placeholder', () => {
        const result = buildFormInput({
            type: 'email',
            placeholder: 'you@example.com',
            borderRadius: 6,
            borderColor: '#d1d5db',
            disabled: false,
        });

        expect(result.html).toBe(
            '<input class="form-input" type="email" placeholder="you@example.com">',
        );
        expect(result.css).toContain('border-radius: 6px;');
        expect(result.css).toContain('border: 1px solid #d1d5db;');
    });

    test('adds the disabled attribute and disabled styling when disabled', () => {
        const result = buildFormInput({
            type: 'text',
            placeholder: '名前',
            borderRadius: 4,
            borderColor: '#d1d5db',
            disabled: true,
        });

        expect(result.html).toBe(
            '<input class="form-input" type="text" placeholder="名前" disabled>',
        );
        expect(result.css).toContain('.form-input:disabled {');
        expect(result.css).toContain('cursor: not-allowed;');
    });

    test.each(['text', 'email', 'password', 'number'] as const)(
        'supports the %s input type',
        (type) => {
            const result = buildFormInput({
                type,
                placeholder: 'placeholder',
                borderRadius: 4,
                borderColor: '#d1d5db',
                disabled: false,
            });

            expect(result.html).toContain(`type="${type}"`);
        },
    );
});

describe('buildFormTextarea', () => {
    test('builds a textarea tag with the given rows and placeholder', () => {
        const result = buildFormTextarea({
            placeholder: 'コメントを入力',
            rows: 4,
            borderRadius: 6,
            borderColor: '#d1d5db',
            disabled: false,
        });

        expect(result.html).toBe(
            '<textarea class="form-textarea" rows="4" placeholder="コメントを入力"></textarea>',
        );
        expect(result.css).toContain('resize: vertical;');
        expect(result.css).toContain('border-radius: 6px;');
    });

    test('adds the disabled attribute and disables resize when disabled', () => {
        const result = buildFormTextarea({
            placeholder: 'コメントを入力',
            rows: 4,
            borderRadius: 6,
            borderColor: '#d1d5db',
            disabled: true,
        });

        expect(result.html).toBe(
            '<textarea class="form-textarea" rows="4" placeholder="コメントを入力" disabled></textarea>',
        );
        expect(result.css).toContain('.form-textarea:disabled {');
        expect(result.css).toContain('resize: none;');
    });
});

describe('buildFormCheck', () => {
    test('builds a checkbox with a label', () => {
        const result = buildFormCheck({
            type: 'checkbox',
            label: '利用規約に同意する',
            checked: false,
            disabled: false,
        });

        expect(result.html).toBe(
            '<label class="form-check">\n' +
                '    <input class="form-check-input" type="checkbox">\n' +
                '    <span class="form-check-label">利用規約に同意する</span>\n' +
                '</label>',
        );
        expect(result.css).toContain('border-radius: 4px;');
    });

    test('builds a checked, disabled radio button with rounded styling', () => {
        const result = buildFormCheck({
            type: 'radio',
            label: 'プラン A',
            checked: true,
            disabled: true,
        });

        expect(result.html).toBe(
            '<label class="form-check">\n' +
                '    <input class="form-check-input" type="radio" checked disabled>\n' +
                '    <span class="form-check-label">プラン A</span>\n' +
                '</label>',
        );
        expect(result.css).toContain('border-radius: 50%;');
    });
});

describe('buildFormSelect', () => {
    test('builds a select with one option per entry', () => {
        const result = buildFormSelect({
            options: ['東京都', '大阪府', '愛知県'],
            borderRadius: 6,
            borderColor: '#d1d5db',
            disabled: false,
        });

        expect(result.html).toBe(
            '<select class="form-select">\n' +
                '    <option>東京都</option>\n' +
                '    <option>大阪府</option>\n' +
                '    <option>愛知県</option>\n' +
                '</select>',
        );
        expect(result.css).toContain('border-radius: 6px;');
    });

    test('adds the disabled attribute when disabled', () => {
        const result = buildFormSelect({
            options: ['A', 'B'],
            borderRadius: 6,
            borderColor: '#d1d5db',
            disabled: true,
        });

        expect(result.html).toContain('<select class="form-select" disabled>');
        expect(result.css).toContain('.form-select:disabled {');
    });
});

describe('buildFormGroup', () => {
    test('builds a label, input, and helper text', () => {
        const result = buildFormGroup({
            label: 'メールアドレス',
            placeholder: 'you@example.com',
            helperText: '登録用のメールアドレスを入力してください',
            hasError: false,
        });

        expect(result.html).toBe(
            '<div class="form-group">\n' +
                '    <label class="form-group-label">メールアドレス</label>\n' +
                '    <input class="form-group-input" type="text" placeholder="you@example.com">\n' +
                '    <p class="form-group-helper">登録用のメールアドレスを入力してください</p>\n' +
                '</div>',
        );
        expect(result.css).toContain('.form-group {');
    });

    test('applies invalid styling classes when hasError is true', () => {
        const result = buildFormGroup({
            label: 'メールアドレス',
            placeholder: 'you@example.com',
            helperText: '有効なメールアドレスを入力してください',
            hasError: true,
        });

        expect(result.html).toContain('class="form-group-input is-invalid"');
        expect(result.html).toContain('class="form-group-helper is-invalid"');
        expect(result.css).toContain('.form-group-input.is-invalid {');
        expect(result.css).toContain('.form-group-helper.is-invalid {');
    });
});

describe('buildHeader', () => {
    test('builds a header with a logo, toggle button, and one link per nav item', () => {
        const result = buildHeader({
            logoText: 'katsuooool',
            navLinks: ['ホーム', 'ツール一覧', 'お問い合わせ'],
            backgroundColor: '#ffffff',
            shadowEnabled: true,
        });

        expect(result.html).toContain(
            '<a href="#" class="site-header-logo">katsuooool</a>',
        );
        expect(result.html).toContain('class="site-header-toggle"');
        expect(result.html).toContain(
            '<a href="#" class="site-nav-link">ホーム</a>',
        );
        expect(result.html).toContain(
            '<a href="#" class="site-nav-link">ツール一覧</a>',
        );
        expect(result.html).toContain(
            '<a href="#" class="site-nav-link">お問い合わせ</a>',
        );
        expect(result.css).toContain(
            'box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);',
        );
        expect(result.css).toContain('@media (max-width: 768px) {');
        expect(result.js).toContain("classList.toggle('is-open')");
    });

    test('removes the box shadow when shadowEnabled is false', () => {
        const result = buildHeader({
            logoText: 'katsuooool',
            navLinks: ['ホーム'],
            backgroundColor: '#ffffff',
            shadowEnabled: false,
        });

        expect(result.css).toContain('box-shadow: none;');
    });
});

describe('buildFooter', () => {
    test('builds a footer with the copyright text and one link per item', () => {
        const result = buildFooter({
            copyrightText: '© 2026 katsuooool',
            links: ['利用規約', 'プライバシーポリシー'],
            backgroundColor: '#f5f5f5',
        });

        expect(result.html).toContain(
            '<p class="site-footer-copyright">© 2026 katsuooool</p>',
        );
        expect(result.html).toContain(
            '<a href="#" class="site-footer-link">利用規約</a>',
        );
        expect(result.html).toContain(
            '<a href="#" class="site-footer-link">プライバシーポリシー</a>',
        );
        expect(result.css).toContain('background-color: #f5f5f5;');
        expect(result.css).toContain('@media (max-width: 768px) {');
    });
});
