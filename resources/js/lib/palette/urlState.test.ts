import { describe, expect, test } from 'vitest';

import { DEFAULT_BASE, parseState, serializeState } from './urlState';

describe('parseState', () => {
    test('空なら既定（やさしい・気分モード）', () => {
        expect(parseState('')).toEqual({
            mode: 'mood',
            mood: 'gentle',
            base: DEFAULT_BASE,
            selected: null,
        });
    });

    test('気分と選択を読む', () => {
        const state = parseState('?mood=trust&p=blue-2');
        expect(state.mode).toBe('mood');
        expect(state.mood).toBe('trust');
        expect(state.selected).toBe('blue-2');
    });

    test('base があれば基準色モード（# 無し・大文字も許容）', () => {
        const state = parseState('?base=2F6F8F&p=triad');
        expect(state.mode).toBe('base');
        expect(state.base).toBe('#2f6f8f');
        expect(state.selected).toBe('triad');
    });

    test('未知の気分・不正な hex・不正な p は既定に戻る', () => {
        expect(parseState('?mood=zzz').mood).toBe('gentle');
        expect(parseState('?base=xyz').mode).toBe('mood');
        expect(parseState('?base=12345').mode).toBe('mood');
        expect(parseState('?p=<script>').selected).toBeNull();
        expect(parseState('?p=' + 'a'.repeat(50)).selected).toBeNull();
    });
});

describe('serializeState', () => {
    test('気分モード', () => {
        expect(
            serializeState({
                mode: 'mood',
                mood: 'warm',
                base: DEFAULT_BASE,
                selected: 'red-1',
            }),
        ).toBe('?mood=warm&p=red-1');
    });

    test('選択なしなら p を付けない', () => {
        expect(
            serializeState({
                mode: 'mood',
                mood: 'warm',
                base: DEFAULT_BASE,
                selected: null,
            }),
        ).toBe('?mood=warm');
    });

    test('基準色モードは # を外す', () => {
        expect(
            serializeState({
                mode: 'base',
                mood: 'gentle',
                base: '#2f6f8f',
                selected: 'mono',
            }),
        ).toBe('?base=2f6f8f&p=mono');
    });

    test('parse と serialize は往復できる', () => {
        const state = parseState('?base=ff0000&p=split');
        expect(parseState(serializeState(state))).toEqual(state);
    });
});
