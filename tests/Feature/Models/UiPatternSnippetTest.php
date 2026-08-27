<?php

use App\Models\UiPatternSnippet;

test('the forCategory scope filters by category and orders by id ascending', function () {
    UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'カードパターン',
        'html' => '<div>card</div>',
        'css' => '.card {}',
    ]);

    $createdFirst = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '先に作成したボタン',
        'html' => '<a>first</a>',
        'css' => 'a {}',
    ]);

    $createdSecond = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '後に作成したボタン',
        'html' => '<a>second</a>',
        'css' => 'a {}',
    ]);

    $results = UiPatternSnippet::query()->forCategory('button')->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('id')->all())->toBe([$createdFirst->id, $createdSecond->id]);
});

test('a snippet defaults to not being premium', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'デフォルト確認',
        'html' => '<a>test</a>',
        'css' => 'a {}',
    ]);

    expect($snippet->is_premium)->toBeFalse();
});

test('a snippet can be marked as premium', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'Premium確認',
        'html' => '<a>test</a>',
        'css' => 'a {}',
        'is_premium' => true,
    ]);

    expect($snippet->fresh()->is_premium)->toBeTrue();
});
