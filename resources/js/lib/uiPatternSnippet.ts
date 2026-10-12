export interface PatternSnippet {
    id: number;
    title: string;
    locked: boolean;
    html?: string;
    css?: string;
    srcdoc?: string;
}

/** プレビュー内から親へ、部品の実寸（余白込み）を知らせるメッセージの種類。 */
export const PREVIEW_SIZE_MESSAGE = 'pattern-preview-size';

// PatternDocumentRenderer::SIZE_REPORTER と同じ内容に保つこと。
export const SIZE_REPORTER = `(function () {
  var wrap = document.getElementById('__pw');
  var sent = '';
  var count = 0;
  function report() {
    var r = wrap.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) { return; }
    var w = Math.ceil(Math.max(r.width, wrap.scrollWidth)) + 32;
    var h = Math.ceil(Math.max(r.height, wrap.scrollHeight)) + 32;
    var key = w + 'x' + h;
    if (key === sent || count >= 8) { return; }
    sent = key;
    count += 1;
    parent.postMessage({ type: 'pattern-preview-size', width: w, height: h }, '*');
  }
  window.addEventListener('load', report);
  if (window.ResizeObserver) { new ResizeObserver(report).observe(wrap); }
  report();
})();`;

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
<div id="__pw" style="display:flex;align-items:center;justify-content:center;width:max-content;min-width:100%;max-width:960px">
${html}
</div>
<script>
${SIZE_REPORTER}
</script>
</body>
</html>`;
}

/**
 * 実寸 (contentW x contentH) の部品を、枠 (boxW x boxH) に収める縮小倍率。拡大はしない（最大 1）。
 * 寸法が不正（0 以下・数値でない）なら、縮小せず 1 を返す。
 */
export function fitScale(
    contentW: number,
    contentH: number,
    boxW: number,
    boxH: number,
): number {
    const valid = [contentW, contentH, boxW, boxH].every(
        (n) => Number.isFinite(n) && n > 0,
    );

    if (!valid) {
        return 1;
    }

    return Math.min(1, boxW / contentW, boxH / contentH);
}
