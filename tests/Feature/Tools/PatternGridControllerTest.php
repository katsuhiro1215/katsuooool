<?php

use App\Models\ToolEvent;

test('the pattern-grid page renders', function () {
    $response = $this->get(route('pattern-grid.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternGrid'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-grid.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-grid')->where('event_type', 'process')->exists())->toBeTrue();
});
