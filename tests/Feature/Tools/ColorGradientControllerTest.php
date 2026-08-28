<?php

use App\Models\ColorExample;
use App\Models\ToolEvent;

test('the color-gradient page renders', function () {
    $response = $this->get(route('color-gradient.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorGradient'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-gradient.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-gradient')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page includes gradient-type examples ordered by copy_count', function () {
    $lowCount = ColorExample::create([
        'type' => 'gradient',
        'title' => '低人気グラデーション',
        'colors' => ['#111111', '#222222'],
        'angle' => 45,
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'gradient',
        'title' => '高人気グラデーション',
        'colors' => ['#333333', '#444444'],
        'angle' => 90,
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'palette',
        'title' => '別typeのパレット',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->get(route('color-gradient.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ColorGradient')
        ->has('examples', 2)
        ->where('examples.0.id', $highCount->id)
        ->where('examples.1.id', $lowCount->id)
    );
});

test('copying a gradient example increments its copy_count', function () {
    $example = ColorExample::create([
        'type' => 'gradient',
        'title' => 'テストグラデーション',
        'colors' => ['#111111', '#222222'],
        'angle' => 90,
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-gradient.examples.copy', $example));

    $response->assertSuccessful();
    expect($example->fresh()->copy_count)->toBe(1);
});

test('copying a non-gradient example via the gradient endpoint returns 404', function () {
    $paletteExample = ColorExample::create([
        'type' => 'palette',
        'title' => 'パレット例',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-gradient.examples.copy', $paletteExample));

    $response->assertStatus(404);
});
