<?php

use App\Models\ToolEvent;

test('the pattern-wave page renders', function () {
    $response = $this->get(route('pattern-wave.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternWave'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-wave.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-wave')->where('event_type', 'process')->exists())->toBeTrue();
});
