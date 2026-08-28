<?php

use App\Models\ColorExample;
use App\Models\ToolEvent;

test('the color-scheme page renders', function () {
    $response = $this->get(route('color-scheme.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorScheme'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-scheme.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-scheme')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page includes scheme-type examples ordered by copy_count', function () {
    $lowCount = ColorExample::create([
        'type' => 'scheme',
        'title' => '低人気の組み合わせ',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'scheme',
        'title' => '高人気の組み合わせ',
        'colors' => ['#333333', '#444444'],
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'palette',
        'title' => '別typeのパレット',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->get(route('color-scheme.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ColorScheme')
        ->has('examples', 2)
        ->where('examples.0.id', $highCount->id)
        ->where('examples.1.id', $lowCount->id)
    );
});

test('copying a scheme example increments its copy_count', function () {
    $example = ColorExample::create([
        'type' => 'scheme',
        'title' => 'テスト組み合わせ',
        'colors' => ['#111111', '#222222'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-scheme.examples.copy', $example));

    $response->assertSuccessful();
    expect($example->fresh()->copy_count)->toBe(1);
});

test('copying a non-scheme example via the scheme endpoint returns 404', function () {
    $paletteExample = ColorExample::create([
        'type' => 'palette',
        'title' => 'パレット例',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-scheme.examples.copy', $paletteExample));

    $response->assertStatus(404);
});
