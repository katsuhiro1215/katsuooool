export const CATEGORY_LABELS: Record<string, string> = {
    button: 'ボタン',
    card: 'カード',
    pagination: 'ページネーション',
    modal: 'モーダル',
    toast: 'トースト',
    loading: 'ローディング',
    skeleton: 'スケルトン',
    'form-input': 'フォーム(テキスト入力)',
    'form-textarea': 'フォーム(テキストエリア)',
    'form-check': 'フォーム(チェックボックス)',
    'form-select': 'フォーム(セレクト)',
    'form-group': 'フォームグループ',
    header: 'ヘッダー',
    footer: 'フッター',
};

export const CATEGORY_OPTIONS = Object.entries(CATEGORY_LABELS).map(
    ([value, label]) => ({ value, label }),
);
