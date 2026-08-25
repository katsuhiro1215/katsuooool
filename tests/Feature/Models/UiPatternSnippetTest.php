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
