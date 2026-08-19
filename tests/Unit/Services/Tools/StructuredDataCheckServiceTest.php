<?php

use App\Services\Tools\StructuredDataCheckService;

test('parses a valid JSON-LD block with no warnings', function () {
    $html = <<<'HTML'
        <html><head>
        <script type="application/ld+json">
        {"@context":"https://schema.org","@type":"Article","headline":"見出し"}
        </script>
        </head></html>
        HTML;

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result)->toHaveCount(1);
    expect($result[0]['valid'])->toBeTrue();
    expect($result[0]['data'])->toBe(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => '見出し']);
    expect($result[0]['warnings'])->toBe([]);
});

test('flags a block missing @context and @type', function () {
    $html = '<script type="application/ld+json">{"headline":"見出しのみ"}</script>';

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result[0]['valid'])->toBeTrue();
    expect($result[0]['warnings'])->toBe(['@context が見つかりません。', '@type が見つかりません。']);
});

test('reports a JSON syntax error without crashing', function () {
    $html = '<script type="application/ld+json">{invalid json}</script>';

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result[0]['valid'])->toBeFalse();
    expect($result[0]['error'])->not->toBeEmpty();
});

test('ignores script tags that are not application/ld+json', function () {
    $html = '<script type="application/javascript">console.log(1);</script>';

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result)->toBe([]);
});

test('handles a top-level JSON-LD array of multiple entities', function () {
    $html = <<<'HTML'
        <script type="application/ld+json">
        [
            {"@context":"https://schema.org","@type":"Person","name":"A"},
            {"@type":"Organization","name":"B"}
        ]
        </script>
        HTML;

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result[0]['valid'])->toBeTrue();
    expect($result[0]['warnings'])->toBe(['@context が見つかりません。']);
});
