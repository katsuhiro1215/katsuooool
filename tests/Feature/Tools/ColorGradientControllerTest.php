<?php

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
