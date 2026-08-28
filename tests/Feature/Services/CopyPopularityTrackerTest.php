<?php

use App\Models\ColorExample;
use App\Services\CopyPopularityTracker;
use Illuminate\Support\Carbon;

test('recording a copy increments copy_count once', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);

    (new CopyPopularityTracker)->record($example, '203.0.113.10');

    expect($example->fresh()->copy_count)->toBe(1);
});

test('the same ip recording twice on the same day only counts once', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);
    $tracker = new CopyPopularityTracker;

    $tracker->record($example, '203.0.113.10');
    $tracker->record($example, '203.0.113.10');

    expect($example->fresh()->copy_count)->toBe(1);
});

test('different ips each count separately', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);
    $tracker = new CopyPopularityTracker;

    $tracker->record($example, '203.0.113.10');
    $tracker->record($example, '203.0.113.20');

    expect($example->fresh()->copy_count)->toBe(2);
});

test('the same ip on a different day counts again', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);
    $tracker = new CopyPopularityTracker;

    Carbon::setTestNow('2026-08-28 10:00:00');
    $tracker->record($example, '203.0.113.10');

    Carbon::setTestNow('2026-08-29 10:00:00');
    $tracker->record($example, '203.0.113.10');

    Carbon::setTestNow();

    expect($example->fresh()->copy_count)->toBe(2);
});

test('a null ip still increments (no dedup guarantee for unresolvable ips)', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);

    (new CopyPopularityTracker)->record($example, null);

    expect($example->fresh()->copy_count)->toBe(1);
});
