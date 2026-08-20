export interface BoxShadowInput {
    x: number;
    y: number;
    blur: number;
    spread: number;
    color: string;
    inset: boolean;
}

export function buildBoxShadow(input: BoxShadowInput): string {
    const prefix = input.inset ? 'inset ' : '';

    return `box-shadow: ${prefix}${input.x}px ${input.y}px ${input.blur}px ${input.spread}px ${input.color};`;
}

export interface BorderRadiusInput {
    topLeft: number;
    topRight: number;
    bottomRight: number;
    bottomLeft: number;
}

export function buildBorderRadius(input: BorderRadiusInput): string {
    return `border-radius: ${input.topLeft}px ${input.topRight}px ${input.bottomRight}px ${input.bottomLeft}px;`;
}

export interface GlassmorphismInput {
    opacity: number;
    blur: number;
    borderOpacity: number;
    shadowOpacity: number;
}

export function buildGlassmorphism(input: GlassmorphismInput): string {
    return [
        `background: rgba(255, 255, 255, ${input.opacity});`,
        `backdrop-filter: blur(${input.blur}px);`,
        `border: 1px solid rgba(255, 255, 255, ${input.borderOpacity});`,
        `box-shadow: 0 8px 32px 0 rgba(31, 38, 135, ${input.shadowOpacity});`,
    ].join('\n');
}

export type FlexDirection = 'row' | 'row-reverse' | 'column' | 'column-reverse';
export type JustifyContent =
    | 'flex-start'
    | 'flex-end'
    | 'center'
    | 'space-between'
    | 'space-around'
    | 'space-evenly';
export type AlignItems =
    'stretch' | 'flex-start' | 'flex-end' | 'center' | 'baseline';
export type FlexWrap = 'nowrap' | 'wrap' | 'wrap-reverse';

export interface FlexboxInput {
    direction: FlexDirection;
    justifyContent: JustifyContent;
    alignItems: AlignItems;
    wrap: FlexWrap;
    gap: number;
}

export function buildFlexbox(input: FlexboxInput): string {
    return [
        'display: flex;',
        `flex-direction: ${input.direction};`,
        `justify-content: ${input.justifyContent};`,
        `align-items: ${input.alignItems};`,
        `flex-wrap: ${input.wrap};`,
        `gap: ${input.gap}px;`,
    ].join('\n');
}

export interface GridInput {
    columns: number;
    rows: number;
    gap: number;
}

export function buildGrid(input: GridInput): string {
    return [
        'display: grid;',
        `grid-template-columns: repeat(${input.columns}, 1fr);`,
        `grid-template-rows: repeat(${input.rows}, 1fr);`,
        `gap: ${input.gap}px;`,
    ].join('\n');
}
