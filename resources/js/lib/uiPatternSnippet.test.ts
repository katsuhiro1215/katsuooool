import { expect, test } from 'vitest';

import { vi } from 'vitest';

import {
    SIZE_REPORTER,
    buildPatternDocument,
    fitScale,
} from './uiPatternSnippet';
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
<div id="__pw" style="display:flex;align-items:center;justify-content:center;width:max-content;min-width:100%;max-width:960px">
<a href="#">Button</a>
</div>
<script>
(function () {
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

// プレビュー内で動く計測スクリプトを、document / window / parent を差し替えて実行する。
function runReporter(rect: { width: number; height: number }, scroll = rect) {
    const callbacks: Record<string, () => void> = {};
    let observe: () => void = () => {};
    const parent = { postMessage: vi.fn() };
    const wrap = {
        getBoundingClientRect: () => rect,
        scrollWidth: scroll.width,
        scrollHeight: scroll.height,
    };
    const win = {
        ResizeObserver: true,
        addEventListener: (name: string, cb: () => void) => {
            callbacks[name] = cb;
        },
    };
    class FakeResizeObserver {
        constructor(cb: () => void) {
            observe = cb;
        }
        observe() {}
    }

    new Function(
        'document',
        'window',
        'parent',
        'ResizeObserver',
        SIZE_REPORTER,
    )({ getElementById: () => wrap }, win, parent, FakeResizeObserver);

    return { parent, callbacks, resize: () => observe(), wrap };
}

test('計測スクリプトは、実寸に余白 32px を足して親へ通知する', () => {
    const { parent } = runReporter({ width: 200, height: 100 });

    expect(parent.postMessage).toHaveBeenCalledWith(
        { type: 'pattern-preview-size', width: 232, height: 132 },
        '*',
    );
});

test('計測スクリプトは、はみ出した分（scrollWidth/Height）も実寸に含める', () => {
    const { parent } = runReporter(
        { width: 200, height: 100 },
        { width: 300, height: 150 },
    );

    expect(parent.postMessage).toHaveBeenCalledWith(
        { type: 'pattern-preview-size', width: 332, height: 182 },
        '*',
    );
});

test('固定配置などで実寸が 0 のときは通知しない（枠いっぱいの表示に任せる）', () => {
    expect(
        runReporter({ width: 0, height: 0 }).parent.postMessage,
    ).not.toHaveBeenCalled();
    expect(
        runReporter({ width: 100, height: 0 }).parent.postMessage,
    ).not.toHaveBeenCalled();
});

test('同じ大きさは重複して通知せず、変わり続けても 8 回で止まる', () => {
    const rect = { width: 100, height: 100 };
    const { parent, callbacks, resize } = runReporter(rect);

    callbacks.load();
    resize();
    expect(parent.postMessage).toHaveBeenCalledTimes(1);

    for (let i = 0; i < 30; i++) {
        rect.height += 10;
        resize();
    }

    expect(parent.postMessage).toHaveBeenCalledTimes(8);
});
