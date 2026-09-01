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

test('strips unsafe link schemes like javascript:', function () {
    $renderer = new MarkdownRenderer;

    $html = $renderer->toHtml('[click me](javascript:alert(1))');

    expect($html)->not->toContain('href="javascript:');
});

test('renders a single line break within a paragraph as <br>', function () {
    $renderer = new MarkdownRenderer;

    $html = $renderer->toHtml("1行目\n2行目");

    expect($html)->toContain("1行目<br />\n2行目");
});
