<?php

use App\Models\ToolEvent;

test('the pattern-chevron page renders', function () {
    $response = $this->get(route('pattern-chevron.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternChevron'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-chevron.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-chevron')->where('event_type', 'process')->exists())->toBeTrue();
});
