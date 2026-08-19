<?php

use App\Services\Tools\OgpCheckService;

test('extracts og and twitter meta tags', function () {
    $html = <<<'HTML'
        <html><head>
            <meta property="og:title" content="サンプルタイトル">
            <meta property="og:description" content="説明文">
            <meta property="og:image" content="https://example.com/img.png">
            <meta property="og:url" content="https://example.com/">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="description" content="無関係なタグ">
        </head><body></body></html>
        HTML;

    $result = (new OgpCheckService)->analyze($html);

    expect($result['tags'])->toBe([
        'og:title' => 'サンプルタイトル',
        'og:description' => '説明文',
        'og:image' => 'https://example.com/img.png',
        'og:url' => 'https://example.com/',
        'twitter:card' => 'summary_large_image',
    ]);
    expect($result['missing_recommended'])->toBe([]);
});

test('reports missing recommended tags', function () {
    $html = '<html><head><meta property="og:title" content="タイトルのみ"></head></html>';

    $result = (new OgpCheckService)->analyze($html);

    expect($result['tags'])->toBe(['og:title' => 'タイトルのみ']);
    expect($result['missing_recommended'])->toBe(['og:description', 'og:image', 'og:url']);
});

test('returns empty tags for HTML with no relevant meta tags', function () {
    $result = (new OgpCheckService)->analyze('<html><head><title>No OGP</title></head></html>');

    expect($result['tags'])->toBe([]);
    expect($result['missing_recommended'])->toBe(['og:title', 'og:description', 'og:image', 'og:url']);
});
