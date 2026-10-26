import { describe, expect, test } from 'vitest';
import { HEARING_STEPS } from './questions';
import { SAMPLE_STATE } from './sample';
import {
    allFields,
    answerText,
    emptyState,
    isAnswered,
    isValid,
    normalize,
    plainText,
    reviewItems,
    stepIndexOf,
} from './sheet';

const field = (id: string) => allFields.find((f) => f.id === id)!;

describe('質問定義', () => {
    test('6ステップで、項目IDが重複しない', () => {
        expect(HEARING_STEPS).toHaveLength(6);
        const ids = allFields.map((f) => f.id);
        expect(new Set(ids).size).toBe(ids.length);
    });

    test('選択式の項目には選択肢がある', () => {
        for (const f of allFields) {
            if (['radio', 'chips', 'select'].includes(f.type)) {
                expect(f.options?.length, f.id).toBeGreaterThan(0);
            }
        }
    });
});

describe('回答の判定', () => {
    test('形式が違う入力は回答済みにならない', () => {
        const state = emptyState();
        state.answers.email = 'abc';
        state.answers.website = 'ftp://example.com';
        state.answers.mainColor = '#fff';
        expect(isValid(state, field('email'))).toBe(false);
        expect(isValid(state, field('website'))).toBe(false);
        expect(isValid(state, field('mainColor'))).toBe(false);
        expect(isAnswered(state, field('email'))).toBe(false);

        state.answers.email = 'a@example.com';
        state.answers.website = 'https://example.com';
        state.answers.mainColor = '#526B4D';
        expect(isAnswered(state, field('email'))).toBe(true);
        expect(isValid(state, field('website'))).toBe(true);
        expect(isValid(state, field('mainColor'))).toBe(true);
    });

    test('確認待ちは回答済みに数えない', () => {
        const state = emptyState();
        state.answers.project = 'テスト';
        state.pending.project = true;
        expect(isAnswered(state, field('project'))).toBe(false);
        expect(answerText(state, field('project'))).toContain('［確認待ち］');
    });

    test('空白だけの入力は未回答', () => {
        const state = emptyState();
        state.answers.project = '   ';
        state.answers.competitors = ['', ' '];
        expect(isAnswered(state, field('project'))).toBe(false);
        expect(isAnswered(state, field('competitors'))).toBe(false);
        expect(answerText(state, field('project'))).toBe('未回答');
    });
});

describe('保存データの検証', () => {
    test('サンプルはそのまま読み込める', () => {
        expect(normalize(JSON.parse(JSON.stringify(SAMPLE_STATE)))).toEqual(
            SAMPLE_STATE,
        );
    });

    test('形式が違うデータは拒否する', () => {
        expect(() => normalize(null)).toThrow();
        expect(() => normalize({ version: 2, answers: {} })).toThrow();
        expect(() => normalize({ version: 1, answers: [] })).toThrow();
    });

    test('知らない項目・選択肢にない値・長すぎる値を落とす', () => {
        const state = normalize({
            version: 1,
            step: 99,
            answers: {
                unknown: 'x',
                type: '存在しない選択肢',
                siteType: ['LP', 'でたらめ'],
                project: 'a'.repeat(20000),
            },
            pending: { project: true, unknown: true },
        });
        expect(state.step).toBe(0);
        expect(state.answers.unknown).toBeUndefined();
        expect(state.answers.type).toBeUndefined();
        expect(state.answers.siteType).toEqual(['LP']);
        expect((state.answers.project as string).length).toBe(10000);
        expect(state.pending).toEqual({ project: true });
    });
});

describe('出力', () => {
    test('「未回答を隠す」で未回答の項目が消える', () => {
        const state = emptyState();
        state.answers.project = 'テストサイト';
        expect(plainText(state, false)).toContain('ご担当者名：未回答');
        const hidden = plainText(state, true);
        expect(hidden).not.toContain('ご担当者名');
        expect(hidden).toContain('プロジェクト名：テストサイト');
    });

    test('確認の一覧は、確認待ち・形式エラー・重点項目の未回答を出す', () => {
        const state = emptyState();
        state.answers.email = 'abc';
        state.pending.deadline = true;
        const { items, waiting, missingCore } = reviewItems(state);
        expect(waiting).toBe(1);
        expect(missingCore).toBeGreaterThan(0);
        expect(items.find((i) => i.field.id === 'deadline')?.reason).toBe(
            '確認待ち',
        );
        expect(items.find((i) => i.field.id === 'email')?.reason).toBe(
            '形式を要確認',
        );
        expect(items.find((i) => i.field.id === 'project')?.reason).toBe(
            '未回答',
        );
    });

    test('項目の属するステップを引ける', () => {
        expect(stepIndexOf('project')).toBe(0);
        expect(stepIndexOf('nextAction')).toBe(5);
        expect(stepIndexOf('nothing')).toBe(-1);
    });
});
