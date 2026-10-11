import { DEFAULT_MOOD, isMoodId } from './moods';
import type { MoodId } from './types';

export const DEFAULT_BASE = '#2f6f8f';

export interface GeneratorState {
    mode: 'mood' | 'base';
    mood: MoodId;
    base: string;
    /** 選択中のパレット id。未選択（先頭を使う）は null。 */
    selected: string | null;
}

const SELECTED_PATTERN = /^[a-z]{1,12}(-\d{1,2})?$/;
const HEX6 = /^[0-9a-f]{6}$/;

export function parseState(search: string): GeneratorState {
    const query = new URLSearchParams(search);
    const baseParam = (query.get('base') ?? '').replace(/^#/, '').toLowerCase();
    const hasBase = HEX6.test(baseParam);
    const moodParam = query.get('mood');
    const selected = query.get('p');

    return {
        mode: hasBase ? 'base' : 'mood',
        mood: isMoodId(moodParam) ? moodParam : DEFAULT_MOOD,
        base: hasBase ? `#${baseParam}` : DEFAULT_BASE,
        selected: selected && SELECTED_PATTERN.test(selected) ? selected : null,
    };
}

export function serializeState(state: GeneratorState): string {
    const query = new URLSearchParams();

    if (state.mode === 'base') {
        query.set('base', state.base.replace(/^#/, ''));
    } else {
        query.set('mood', state.mood);
    }

    if (state.selected) {
        query.set('p', state.selected);
    }

    return `?${query.toString()}`;
}
