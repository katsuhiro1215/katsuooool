<?php

use App\Services\PatternDocumentRenderer;

test('build produces the exact wrapper document byte-for-byte', function () {
    // NOTE: This expected string must be kept in exact structural sync with
    // resources/js/lib/uiPatternSnippet.test.ts's expected string. If you
    // change the wrapper template in either PatternDocumentRenderer::build()
    // or buildPatternDocument(), update both tests' expected strings.
    $renderer = new PatternDocumentRenderer;

    $result = $renderer->build('<a href="#">Button</a>', 'a { color: red; }');

    $expected = <<<'HTML'
<!doctype html>
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
</html>
HTML;

    expect($result)->toBe($expected);
});
