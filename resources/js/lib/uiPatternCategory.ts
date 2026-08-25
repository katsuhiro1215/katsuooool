export const CATEGORY_LABELS: Record<string, string> = {
    button: 'ボタン',
};

export const CATEGORY_OPTIONS = Object.entries(CATEGORY_LABELS).map(
    ([value, label]) => ({ value, label }),
);
