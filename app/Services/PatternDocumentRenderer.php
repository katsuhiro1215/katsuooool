<?php

namespace App\Services;

class PatternDocumentRenderer
{
    // プレビュー内から親へ、部品の実寸を知らせるスクリプト。resources/js/lib/uiPatternSnippet.ts の SIZE_REPORTER と同じ内容に保つこと。
    private const SIZE_REPORTER = <<<'JS'
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
JS;

    // Keep this wrapper template structurally identical to buildPatternDocument() in resources/js/lib/uiPatternSnippet.ts.
    public function build(string $html, string $css): string
    {
        $script = self::SIZE_REPORTER;

        return <<<HTML
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
  {$css}
</style>
</head>
<body>
<div id="__pw" style="display:flex;align-items:center;justify-content:center;width:max-content;min-width:100%;max-width:960px">
{$html}
</div>
<script>
{$script}
</script>
</body>
</html>
HTML;
    }
}
