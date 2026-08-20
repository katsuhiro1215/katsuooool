import { describe, expect, test } from 'vitest';

import {
    buildButton,
    buildCard,
    buildModal,
    buildPagination,
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
