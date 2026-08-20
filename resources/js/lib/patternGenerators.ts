export interface DotPatternInput {
    backgroundColor: string;
    patternColor: string;
    radius: number;
    spacing: number;
}

export function buildDotPattern(input: DotPatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: radial-gradient(circle, ${input.patternColor} ${input.radius}px, transparent ${input.radius}px);`,
        `background-size: ${input.spacing}px ${input.spacing}px;`,
    ].join('\n');
}

export interface GridPatternInput {
    backgroundColor: string;
    lineColor: string;
    lineWidth: number;
    cellSize: number;
}

export function buildGridPattern(input: GridPatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: linear-gradient(to right, ${input.lineColor} ${input.lineWidth}px, transparent ${input.lineWidth}px), linear-gradient(to bottom, ${input.lineColor} ${input.lineWidth}px, transparent ${input.lineWidth}px);`,
        `background-size: ${input.cellSize}px ${input.cellSize}px;`,
    ].join('\n');
}

export interface StripePatternInput {
    backgroundColor: string;
    patternColor: string;
    stripeWidth: number;
    angle: number;
}

export function buildStripePattern(input: StripePatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: repeating-linear-gradient(${input.angle}deg, ${input.patternColor} 0px ${input.stripeWidth}px, ${input.backgroundColor} ${input.stripeWidth}px ${input.stripeWidth * 2}px);`,
    ].join('\n');
}

export interface CheckerboardPatternInput {
    backgroundColor: string;
    patternColor: string;
    cellSize: number;
}

export function buildCheckerboardPattern(
    input: CheckerboardPatternInput,
): string {
    const size = input.cellSize * 2;

    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: linear-gradient(45deg, ${input.patternColor} 25%, transparent 25%, transparent 75%, ${input.patternColor} 75%), linear-gradient(45deg, ${input.patternColor} 25%, transparent 25%, transparent 75%, ${input.patternColor} 75%);`,
        `background-size: ${size}px ${size}px;`,
        `background-position: 0 0, ${input.cellSize}px ${input.cellSize}px;`,
    ].join('\n');
}

export interface ChevronPatternInput {
    backgroundColor: string;
    patternColor: string;
    width: number;
    height: number;
}

export function buildChevronPattern(input: ChevronPatternInput): string {
    const svg =
        `<svg xmlns='http://www.w3.org/2000/svg' width='${input.width}' height='${input.height}' ` +
        `viewBox='0 0 ${input.width} ${input.height}'>` +
        `<polyline points='0,${input.height} ${input.width / 2},0 ${input.width},${input.height}' ` +
        `stroke='${input.patternColor}' fill='none' stroke-width='2'/></svg>`;
    const encoded = encodeURIComponent(svg);

    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: url("data:image/svg+xml,${encoded}");`,
        `background-size: ${input.width}px ${input.height}px;`,
    ].join('\n');
}

export interface WavePatternInput {
    backgroundColor: string;
    patternColor: string;
    waveHeight: number;
    wavelength: number;
}

export function buildWavePattern(input: WavePatternInput): string {
    const height = input.waveHeight * 2;
    const half = input.wavelength / 2;
    const quarter = input.wavelength / 4;
    const svg =
        `<svg xmlns='http://www.w3.org/2000/svg' width='${input.wavelength}' height='${height}' ` +
        `viewBox='0 0 ${input.wavelength} ${height}'>` +
        `<path d='M0 ${input.waveHeight} Q${quarter} 0 ${half} ${input.waveHeight} T${input.wavelength} ${input.waveHeight}' ` +
        `stroke='${input.patternColor}' fill='none' stroke-width='2'/></svg>`;
    const encoded = encodeURIComponent(svg);

    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: url("data:image/svg+xml,${encoded}");`,
        `background-size: ${input.wavelength}px ${height}px;`,
    ].join('\n');
}
