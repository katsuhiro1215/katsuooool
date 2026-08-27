<?php

use App\Services\PatternDocumentRenderer;

test('build embeds the given html inside the body and the css inside a style tag', function () {
    $renderer = new PatternDocumentRenderer;

    $result = $renderer->build('<a href="#">Button</a>', 'a { color: red; }');

    expect($result)->toContain('<a href="#">Button</a>');
    expect($result)->toContain('a { color: red; }');
    expect($result)->toContain('<style>');
    expect($result)->toContain('<!doctype html>');
});
