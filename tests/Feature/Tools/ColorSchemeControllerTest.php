<?php

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
