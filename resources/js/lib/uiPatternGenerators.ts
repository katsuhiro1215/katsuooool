export type ButtonSize = 'small' | 'medium' | 'large';

export interface ButtonInput {
    backgroundColor: string;
    textColor: string;
    size: ButtonSize;
    borderRadius: number;
    borderEnabled: boolean;
    borderColor: string;
}

export interface ButtonOutput {
    html: string;
    css: string;
}

const BUTTON_SIZE_STYLES: Record<
    ButtonSize,
    { padding: string; fontSize: string }
> = {
    small: { padding: '6px 12px', fontSize: '14px' },
    medium: { padding: '10px 20px', fontSize: '16px' },
    large: { padding: '14px 28px', fontSize: '18px' },
};

export function buildButton(input: ButtonInput): ButtonOutput {
    const { padding, fontSize } = BUTTON_SIZE_STYLES[input.size];
    const border = input.borderEnabled
        ? `border: 1px solid ${input.borderColor};`
        : 'border: none;';

    const css = [
        '.btn {',
        `    background-color: ${input.backgroundColor};`,
        `    color: ${input.textColor};`,
        `    padding: ${padding};`,
        `    font-size: ${fontSize};`,
        `    border-radius: ${input.borderRadius}px;`,
        `    ${border}`,
        '    cursor: pointer;',
        '}',
    ].join('\n');

    return {
        html: '<button class="btn">ボタン</button>',
        css,
    };
}
