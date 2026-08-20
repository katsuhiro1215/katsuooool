<?php

use App\Models\ToolEvent;

test('the ui-pattern-card page renders', function () {
    $response = $this->get(route('ui-pattern-card.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternCard'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-card.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-card')->where('event_type', 'process')->exists())->toBeTrue();
});
