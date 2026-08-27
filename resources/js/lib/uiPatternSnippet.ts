export interface PatternSnippet {
    id: number;
    title: string;
    locked: boolean;
    html?: string;
    css?: string;
    srcdoc?: string;
}

// Keep this wrapper template structurally identical to PatternDocumentRenderer::build() in app/Services/PatternDocumentRenderer.php.
export function buildPatternDocument(html: string, css: string): string {
    return `<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<style>
  html, body {
    margin: 0;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    box-sizing: border-box;
  }
  ${css}
</style>
</head>
<body>
${html}
</body>
</html>`;
}
