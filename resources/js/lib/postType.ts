export const POST_TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

export const POST_TYPE_OPTIONS = Object.entries(POST_TYPE_LABELS).map(
    ([value, label]) => ({ value, label }),
);
