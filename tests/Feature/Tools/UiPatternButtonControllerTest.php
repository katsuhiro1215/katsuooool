<?php

use App\Models\ToolEvent;

test('the ui-pattern-button page renders', function () {
    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternButton'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-button.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-button')->where('event_type', 'process')->exists())->toBeTrue();
});
