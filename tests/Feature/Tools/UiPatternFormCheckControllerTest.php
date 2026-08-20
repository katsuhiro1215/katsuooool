<?php

use App\Models\ToolEvent;

test('the ui-pattern-form-check page renders', function () {
    $response = $this->get(route('ui-pattern-form-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFormCheck'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-form-check.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-form-check')->where('event_type', 'process')->exists())->toBeTrue();
});
