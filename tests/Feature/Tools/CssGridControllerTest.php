<?php

use App\Models\ToolEvent;

test('the css-grid page renders', function () {
    $response = $this->get(route('css-grid.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssGrid'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-grid.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-grid')->where('event_type', 'process')->exists())->toBeTrue();
});
