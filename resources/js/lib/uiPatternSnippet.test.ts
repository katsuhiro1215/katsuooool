import { expect, test } from 'vitest';

import { buildPatternDocument, fitScale } from './uiPatternSnippet';
import type { PatternSnippet } from './uiPatternSnippet';

test('buildPatternDocument produces the exact wrapper document byte-for-byte', () => {
    // NOTE: This expected string must be kept in exact structural sync with
    // tests/Unit/Services/PatternDocumentRendererTest.php's expected string.
    // If you change the wrapper template in either buildPatternDocument()
    // or PatternDocumentRenderer::build(), update both tests' expected strings.
    const result = buildPatternDocument(
        '<a href="#">Button</a>',
        'a { color: red; }',
    );

    const expected = `<!doctype html>
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
  a { color: red; }
</style>
</head>
<body>
<div id="__pw" style="width:max-content;max-width:640px">
<a href="#">Button</a>
</div>
<script>
(function () {
  var wrap = document.getElementById('__pw');
  var sent = '';
  function report() {
    var r = wrap.getBoundingClientRect();
    var w = Math.ceil(Math.max(r.width, wrap.scrollWidth)) + 32;
    var h = Math.ceil(Math.max(r.height, wrap.scrollHeight)) + 32;
    var key = w + 'x' + h;
    if (key === sent) { return; }
    sent = key;
    parent.postMessage({ type: 'pattern-preview-size', width: w, height: h }, '*');
  }
  window.addEventListener('load', report);
  if (window.ResizeObserver) { new ResizeObserver(report).observe(wrap); }
  report();
})();
</script>
</body>
</html>`;

    expect(result).toBe(expected);
});

test('PatternSnippet accepts both locked and unlocked shapes', () => {
    const unlocked: PatternSnippet = {
        id: 1,
        title: 'サンプル',
        locked: false,
        html: '<a>a</a>',
        css: 'a {}',
    };
    const locked: PatternSnippet = {
        id: 2,
        title: 'Premium',
        locked: true,
        srcdoc: '<html></html>',
    };

    expect(unlocked.locked).toBe(false);
    expect(locked.locked).toBe(true);
});

test('fitScale は枠に収まるよう縮小し、拡大はしない', () => {
    expect(fitScale(200, 100, 400, 200)).toBe(1);
    expect(fitScale(800, 100, 400, 200)).toBe(0.5);
    expect(fitScale(100, 400, 400, 200)).toBe(0.5);
    expect(fitScale(800, 800, 400, 200)).toBe(0.25);
});

test('fitScale は不正な寸法のとき 1 を返す', () => {
    expect(fitScale(0, 100, 400, 200)).toBe(1);
    expect(fitScale(100, 100, 0, 200)).toBe(1);
    expect(fitScale(Number.NaN, 100, 400, 200)).toBe(1);
});
