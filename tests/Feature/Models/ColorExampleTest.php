<?php

use App\Models\ColorExample;

test('ofType filters by type and orders by copy_count desc then id asc', function () {
    $lowCount = ColorExample::create([
        'type' => 'palette',
        'title' => '低人気パレット',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'palette',
        'title' => '高人気パレット',
        'colors' => ['#333333', '#444444'],
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'gradient',
        'title' => '別typeのグラデーション',
        'colors' => ['#000000', '#ffffff'],
        'angle' => 90,
        'copy_count' => 100,
    ]);

    $results = ColorExample::ofType('palette')->get();

    expect($results)->toHaveCount(2);
    expect($results->first()->id)->toBe($highCount->id);
    expect($results->last()->id)->toBe($lowCount->id);
});

test('colors is cast to an array', function () {
    $example = ColorExample::create([
        'type' => 'scheme',
        'title' => '補色',
        'colors' => ['#3366ff', '#ff9933'],
    ]);

    $fresh = ColorExample::find($example->id);

    expect($fresh->colors)->toBe(['#3366ff', '#ff9933']);
});

test('copy_count defaults to zero', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'デフォルト値',
        'colors' => ['#111111', '#222222'],
    ]);

    expect($example->copy_count)->toBe(0);
});
