<?php

use App\Models\ColorExample;
use App\Models\ToolEvent;

test('the color-palette page renders', function () {
    $response = $this->get(route('color-palette.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorPalette'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-palette.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-palette')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page includes palette-type examples ordered by copy_count', function () {
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
    ]);

    $response = $this->get(route('color-palette.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ColorPalette')
        ->has('examples', 2)
        ->where('examples.0.id', $highCount->id)
        ->where('examples.1.id', $lowCount->id)
    );
});

test('copying a palette example increments its copy_count', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-palette.examples.copy', $example));

    $response->assertSuccessful();
    expect($example->fresh()->copy_count)->toBe(1);
});

test('copying a non-palette example via the palette endpoint returns 404', function () {
    $gradientExample = ColorExample::create([
        'type' => 'gradient',
        'title' => 'グラデーション例',
        'colors' => ['#000000', '#ffffff'],
        'angle' => 90,
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-palette.examples.copy', $gradientExample));

    $response->assertStatus(404);
});
