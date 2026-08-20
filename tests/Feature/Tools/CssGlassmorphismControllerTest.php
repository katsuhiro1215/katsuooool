<?php

use App\Models\ToolEvent;

test('the css-glassmorphism page renders', function () {
    $response = $this->get(route('css-glassmorphism.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssGlassmorphism'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-glassmorphism.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-glassmorphism')->where('event_type', 'process')->exists())->toBeTrue();
});
