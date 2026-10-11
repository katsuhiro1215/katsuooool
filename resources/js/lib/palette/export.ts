import { ROLE_INFO } from './roles';
import { ROLE_KEYS } from './types';
import type { PaletteColors } from './types';

/** `--color-*` の名前にしておくと、Tailwind v4 の `@theme` にもそのまま貼れる。 */
export function toCssVariables(colors: PaletteColors): string {
    const lines = ROLE_KEYS.map(
        (key, index) => `  --color-${key}: ${colors[index]};`,
    );

    return [':root {', ...lines, '}'].join('\n');
}

export function toTailwind(colors: PaletteColors): string {
    const lines = ROLE_KEYS.map(
        (key, index) => `        ${key}: '${colors[index]}',`,
    );

    return [
        '// tailwind.config.js',
        'export default {',
        '  theme: {',
        '    extend: {',
        '      colors: {',
        ...lines,
        '      },',
        '    },',
        '  },',
        '};',
    ].join('\n');
}

export function toHexList(colors: PaletteColors): string {
    return ROLE_KEYS.map(
        (key, index) => `${ROLE_INFO[key].label} ${colors[index]}`,
    ).join('\n');
}
