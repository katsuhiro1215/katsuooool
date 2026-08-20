<?php

use App\Models\ToolEvent;

test('the pattern-dot page renders', function () {
    $response = $this->get(route('pattern-dot.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternDot'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-dot.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-dot')->where('event_type', 'process')->exists())->toBeTrue();
});
