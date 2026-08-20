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

export type CardVariant = 'plain' | 'image';

export interface CardInput {
    variant: CardVariant;
    backgroundColor: string;
    borderRadius: number;
    shadowEnabled: boolean;
    padding: number;
    title: string;
    text: string;
}

export interface CardOutput {
    html: string;
    css: string;
}

const CARD_SHADOW = '0 1px 3px rgba(0, 0, 0, 0.1)';

export function buildCard(input: CardInput): CardOutput {
    const boxShadow = input.shadowEnabled ? CARD_SHADOW : 'none';

    if (input.variant === 'image') {
        const html = [
            '<div class="card">',
            '    <img class="card-image" src="https://placehold.co/400x200" alt="">',
            '    <div class="card-body">',
            `        <h3 class="card-title">${input.title}</h3>`,
            `        <p class="card-text">${input.text}</p>`,
            '    </div>',
            '</div>',
        ].join('\n');

        const css = [
            '.card {',
            `    background-color: ${input.backgroundColor};`,
            `    border-radius: ${input.borderRadius}px;`,
            `    box-shadow: ${boxShadow};`,
            '    overflow: hidden;',
            '}',
            '',
            '.card-image {',
            '    display: block;',
            '    width: 100%;',
            '    height: 160px;',
            '    object-fit: cover;',
            '}',
            '',
            '.card-body {',
            `    padding: ${input.padding}px;`,
            '}',
            '',
            '.card-title {',
            '    margin: 0 0 8px;',
            '    font-size: 18px;',
            '    font-weight: bold;',
            '}',
            '',
            '.card-text {',
            '    margin: 0;',
            '    font-size: 14px;',
            '    color: #666;',
            '}',
        ].join('\n');

        return { html, css };
    }

    const html = [
        '<div class="card">',
        `    <h3 class="card-title">${input.title}</h3>`,
        `    <p class="card-text">${input.text}</p>`,
        '</div>',
    ].join('\n');

    const css = [
        '.card {',
        `    background-color: ${input.backgroundColor};`,
        `    border-radius: ${input.borderRadius}px;`,
        `    box-shadow: ${boxShadow};`,
        `    padding: ${input.padding}px;`,
        '}',
        '',
        '.card-title {',
        '    margin: 0 0 8px;',
        '    font-size: 18px;',
        '    font-weight: bold;',
        '}',
        '',
        '.card-text {',
        '    margin: 0;',
        '    font-size: 14px;',
        '    color: #666;',
        '}',
    ].join('\n');

    return { html, css };
}
