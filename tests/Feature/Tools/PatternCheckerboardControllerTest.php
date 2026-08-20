<?php

use App\Models\ToolEvent;

test('the pattern-checkerboard page renders', function () {
    $response = $this->get(route('pattern-checkerboard.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternCheckerboard'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-checkerboard.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-checkerboard')->where('event_type', 'process')->exists())->toBeTrue();
});
