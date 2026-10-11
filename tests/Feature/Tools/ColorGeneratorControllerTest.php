<?php

use App\Models\ToolEvent;

test('the color-generator page renders', function () {
    $response = $this->get('/color-generator');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorGenerator'));
});

test('posting to the track endpoint logs a process event', function () {
    $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/color-generator/track')
        ->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-generator')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the old color-scheme and color-palette pages redirect permanently to the generator', function (string $path) {
    $this->get($path)->assertStatus(301)->assertRedirect('/color-generator');
})->with(['/color-scheme', '/color-palette']);

test('the tool list has the generator and no longer lists the merged tools', function () {
    $slugs = collect(config('tools'))->pluck('slug');

    expect($slugs)->toContain('color-generator')
        ->and($slugs)->not->toContain('color-scheme')
        ->and($slugs)->not->toContain('color-palette');
});
