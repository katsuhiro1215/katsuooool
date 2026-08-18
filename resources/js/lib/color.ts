export interface Rgb {
    r: number;
    g: number;
    b: number;
}

export interface Hsl {
    h: number;
    s: number;
    l: number;
}

const HEX_PATTERN = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i;

export function parseHex(hex: string): Rgb | null {
    const match = HEX_PATTERN.exec(hex.trim());

    if (!match) {
        return null;
    }

    return {
        r: parseInt(match[1], 16),
        g: parseInt(match[2], 16),
        b: parseInt(match[3], 16),
    };
}

function clampByte(value: number): number {
    return Math.min(255, Math.max(0, Math.round(value)));
}

export function rgbToHex({ r, g, b }: Rgb): string {
    const toHex = (value: number) =>
        clampByte(value).toString(16).padStart(2, '0');

    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

export function rgbToHsl({ r, g, b }: Rgb): Hsl {
    const rn = r / 255;
    const gn = g / 255;
    const bn = b / 255;

    const max = Math.max(rn, gn, bn);
    const min = Math.min(rn, gn, bn);
    const delta = max - min;

    let h = 0;

    if (delta !== 0) {
        if (max === rn) {
            h = 60 * (((gn - bn) / delta) % 6);
        } else if (max === gn) {
            h = 60 * ((bn - rn) / delta + 2);
        } else {
            h = 60 * ((rn - gn) / delta + 4);
        }
    }

    if (h < 0) {
        h += 360;
    }

    const l = (max + min) / 2;
    const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1));

    return {
        h: Math.round(h),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

export function hslToRgb({ h, s, l }: Hsl): Rgb {
    const hn = ((h % 360) + 360) % 360;
    const sn = Math.min(100, Math.max(0, s)) / 100;
    const ln = Math.min(100, Math.max(0, l)) / 100;

    const c = (1 - Math.abs(2 * ln - 1)) * sn;
    const x = c * (1 - Math.abs(((hn / 60) % 2) - 1));
    const m = ln - c / 2;

    let [rp, gp, bp] = [0, 0, 0];

    if (hn < 60) {
        [rp, gp, bp] = [c, x, 0];
    } else if (hn < 120) {
        [rp, gp, bp] = [x, c, 0];
    } else if (hn < 180) {
        [rp, gp, bp] = [0, c, x];
    } else if (hn < 240) {
        [rp, gp, bp] = [0, x, c];
    } else if (hn < 300) {
        [rp, gp, bp] = [x, 0, c];
    } else {
        [rp, gp, bp] = [c, 0, x];
    }

    return {
        r: clampByte((rp + m) * 255),
        g: clampByte((gp + m) * 255),
        b: clampByte((bp + m) * 255),
    };
}

export function hexToHsl(hex: string): Hsl | null {
    const rgb = parseHex(hex);

    return rgb ? rgbToHsl(rgb) : null;
}

export function hslToHex(hsl: Hsl): string {
    return rgbToHex(hslToRgb(hsl));
}

/**
 * WCAG 2.1 relative luminance: https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
 */
export function relativeLuminance({ r, g, b }: Rgb): number {
    const linearize = (channel: number) => {
        const c = channel / 255;

        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    };

    return (
        0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b)
    );
}

/**
 * WCAG 2.1 contrast ratio (1:1 to 21:1): https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio
 */
export function contrastRatio(a: Rgb, b: Rgb): number {
    const l1 = relativeLuminance(a);
    const l2 = relativeLuminance(b);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);

    return (lighter + 0.05) / (darker + 0.05);
}

export function rotateHue(hex: string, degrees: number): string {
    // A 0-degree rotation must be a true identity: routing it through the
    // integer-rounded HSL round-trip below can shift a channel by a few
    // units (e.g. #02d8e6 -> #02d4e3), which is visibly wrong for the
    // offset-0 entry of a color scheme (the user's own chosen color).
    if (((degrees % 360) + 360) % 360 === 0) {
        const rgb = parseHex(hex);

        return rgb ? rgbToHex(rgb) : hex;
    }

    const hsl = hexToHsl(hex);

    if (!hsl) {
        return hex;
    }

    return hslToHex({ ...hsl, h: hsl.h + degrees });
}

export function randomHex(): string {
    const hue = Math.floor(Math.random() * 360);
    const saturation = 55 + Math.floor(Math.random() * 35);
    const lightness = 40 + Math.floor(Math.random() * 35);

    return hslToHex({ h: hue, s: saturation, l: lightness });
}
