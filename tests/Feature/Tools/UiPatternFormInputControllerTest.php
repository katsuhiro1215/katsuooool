<?php

use App\Models\ToolEvent;

test('the ui-pattern-form-input page renders', function () {
    $response = $this->get(route('ui-pattern-form-input.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFormInput'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-form-input.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-form-input')->where('event_type', 'process')->exists())->toBeTrue();
});
