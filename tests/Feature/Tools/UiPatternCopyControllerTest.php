<?php

use App\Models\UiPatternSnippet;

test('posting to the copy endpoint increments the snippet copy_count', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'テストボタン',
        'html' => '<a href="#">x</a>',
        'css' => 'a {}',
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    $response->assertSuccessful();
    expect($snippet->fresh()->copy_count)->toBe(1);
});

test('posting twice from the same ip on the same day only counts once', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'テストカード',
        'html' => '<div>x</div>',
        'css' => '.card {}',
    ]);

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    expect($snippet->fresh()->copy_count)->toBe(1);
});
