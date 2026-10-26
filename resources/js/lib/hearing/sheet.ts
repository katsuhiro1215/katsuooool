import { HEARING_STEPS  } from './questions';
import type {HearingField} from './questions';

export const STORAGE_KEY = 'katsuooool-hearing-sheet-v1';
export const MAX_IMPORT_BYTES = 2 * 1024 * 1024;
export const MAX_ROWS = 30;
const MAX_LENGTH = 10000;

export interface SheetState {
    version: 1;
    step: number;
    answers: Record<string, string | string[]>;
    pending: Record<string, boolean>;
    updatedAt: string | null;
}

export const allFields: HearingField[] = HEARING_STEPS.flatMap((step) =>
    step.groups.flatMap((group) => group.fields),
);

export const stepFields = (index: number): HearingField[] =>
    HEARING_STEPS[index].groups.flatMap((group) => group.fields);

export function emptyState(): SheetState {
    return {
        version: 1,
        step: 0,
        answers: {},
        pending: {},
        updatedAt: null,
    };
}

export function hasAnswer(state: SheetState, field: HearingField): boolean {
    const value = state.answers[field.id];

    return Array.isArray(value)
        ? value.some((v) => v.trim() !== '')
        : String(value ?? '').trim() !== '';
}

export function isValid(state: SheetState, field: HearingField): boolean {
    const value = state.answers[field.id];

    if (!hasAnswer(state, field) || Array.isArray(value)) {
        return true;
    }

    const text = String(value);

    if (field.type === 'email') {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text);
    }

    if (field.type === 'url') {
        try {
            return ['http:', 'https:'].includes(new URL(text).protocol);
        } catch {
            return false;
        }
    }

    if (field.type === 'color') {
        return /^#[0-9a-f]{6}$/i.test(text);
    }

    if (field.type === 'date') {
        return (
            /^\d{4}-\d{2}-\d{2}$/.test(text) && !Number.isNaN(Date.parse(text))
        );
    }

    return true;
}

export const errorMessage = (field: HearingField): string =>
    ({
        email: 'メールアドレスの形式をご確認ください。',
        url: 'https:// または http:// から始まるURLを入力してください。',
        color: '#から始まる6桁のHEXコードを入力してください。',
        date: '日付の形式をご確認ください。',
    })[field.type as string] ?? '';

/** 回答済み = 入力があり、形式が正しく、確認待ちではない */
export const isAnswered = (state: SheetState, field: HearingField): boolean =>
    hasAnswer(state, field) &&
    isValid(state, field) &&
    !state.pending[field.id];

/** 保存データ・読み込んだJSONを検証し、知っている項目だけを取り出す。形式が違えば例外。 */
export function normalize(raw: unknown): SheetState {
    const data = raw as Partial<SheetState> | null;

    if (
        !data ||
        data.version !== 1 ||
        !data.answers ||
        typeof data.answers !== 'object' ||
        Array.isArray(data.answers)
    ) {
        throw new Error('形式が違います');
    }

    const clean = emptyState();

    if (
        Number.isInteger(data.step) &&
        (data.step as number) >= 0 &&
        (data.step as number) < HEARING_STEPS.length
    ) {
        clean.step = data.step as number;
    }

    if (
        typeof data.updatedAt === 'string' &&
        !Number.isNaN(Date.parse(data.updatedAt))
    ) {
        clean.updatedAt = data.updatedAt;
    }

    for (const field of allFields) {
        const value = data.answers[field.id];

        if (field.type === 'chips' || field.type === 'repeat') {
            if (Array.isArray(value)) {
                clean.answers[field.id] = value
                    .filter(
                        (v): v is string =>
                            typeof v === 'string' &&
                            (field.type === 'repeat' ||
                                (field.options ?? []).includes(v)),
                    )
                    .slice(0, MAX_ROWS)
                    .map((v) => v.slice(0, MAX_LENGTH));
            }
        } else if (
            typeof value === 'string' &&
            (!field.options || field.options.includes(value))
        ) {
            clean.answers[field.id] = value.slice(0, MAX_LENGTH);
        }

        if (data.pending?.[field.id] === true) {
            clean.pending[field.id] = true;
        }
    }

    return clean;
}

export function answerText(state: SheetState, field: HearingField): string {
    const value = state.answers[field.id];
    const text = Array.isArray(value)
        ? value
              .filter((v) => v.trim())
              .join(field.type === 'repeat' ? '\n' : ' / ')
        : String(value ?? '').trim();
    const pending = state.pending[field.id];

    return `${pending ? '［確認待ち］ ' : ''}${isValid(state, field) ? '' : '［形式を要確認］ '}${text || (pending ? '次回ヒアリングで確認' : '未回答')}`;
}

export function dateText(state: SheetState): string {
    return state.updatedAt
        ? new Date(state.updatedAt).toLocaleDateString('ja-JP', {
              timeZone: 'Asia/Tokyo',
          })
        : '—';
}

export const visibleFields = (
    state: SheetState,
    index: number,
    hideEmpty: boolean,
): HearingField[] =>
    stepFields(index).filter(
        (f) => !hideEmpty || hasAnswer(state, f) || state.pending[f.id],
    );

export function plainText(state: SheetState, hideEmpty: boolean): string {
    const answered = allFields.filter((f) => isAnswered(state, f)).length;
    const pending = allFields.filter((f) => state.pending[f.id]).length;

    return [
        'ヒアリングシート',
        String(state.answers.project || 'プロジェクト名未入力'),
        String(state.answers.company || ''),
        `更新日：${dateText(state)}`,
        `回答：${answered}/${allFields.length}　確認待ち：${pending}`,
        ...HEARING_STEPS.flatMap((step, i) => {
            const fields = visibleFields(state, i, hideEmpty);

            return fields.length
                ? [
                      '',
                      `■ ${String(i + 1).padStart(2, '0')} ${step.title}`,
                      ...fields.map(
                          (f) => `${f.label}：${answerText(state, f)}`,
                      ),
                  ]
                : [];
        }),
    ].join('\n');
}

export interface ReviewItem {
    field: HearingField;
    reason: '確認待ち' | '形式を要確認' | '未回答';
}

export function reviewItems(state: SheetState): {
    waiting: number;
    missingCore: number;
    items: ReviewItem[];
} {
    const waiting = allFields.filter((f) => state.pending[f.id]);
    const missing = allFields.filter(
        (f) => f.core && !isAnswered(state, f) && !state.pending[f.id],
    );
    const invalid = allFields.filter(
        (f) => !isValid(state, f) && !state.pending[f.id],
    );
    const set = new Set([...waiting, ...missing, ...invalid]);

    return {
        waiting: waiting.length,
        missingCore: missing.length,
        items: [...set].map((field) => ({
            field,
            reason: state.pending[field.id]
                ? '確認待ち'
                : !isValid(state, field)
                  ? '形式を要確認'
                  : '未回答',
        })),
    };
}

export const stepIndexOf = (fieldId: string): number =>
    HEARING_STEPS.findIndex((_, i) =>
        stepFields(i).some((f) => f.id === fieldId),
    );
