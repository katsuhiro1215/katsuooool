<?php

use App\Services\MarkdownRenderer;

test('converts markdown headings and links to html', function () {
    $renderer = new MarkdownRenderer;

    $html = $renderer->toHtml("# 見出し\n\n[リンク](https://example.com)");

    expect($html)->toContain('<h1>見出し</h1>');
    expect($html)->toContain('<a href="https://example.com">リンク</a>');
});

test('escapes raw html tags in the input', function () {
    $renderer = new MarkdownRenderer;

    $html = $renderer->toHtml('<script>alert(1)</script>');

    expect($html)->not->toContain('<script>alert(1)</script>');
});
