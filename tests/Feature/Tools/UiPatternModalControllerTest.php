<?php

use App\Models\ToolEvent;

test('the ui-pattern-modal page renders', function () {
    $response = $this->get(route('ui-pattern-modal.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternModal'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-modal.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-modal')->where('event_type', 'process')->exists())->toBeTrue();
});
