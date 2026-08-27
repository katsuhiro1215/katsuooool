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
<a href="#">Button</a>
</body>
</html>
HTML;

    expect($result)->toBe($expected);
});
