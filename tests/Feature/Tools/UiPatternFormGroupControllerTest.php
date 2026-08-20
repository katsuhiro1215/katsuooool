<?php

use App\Models\ToolEvent;

test('the ui-pattern-form-group page renders', function () {
    $response = $this->get(route('ui-pattern-form-group.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFormGroup'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-form-group.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-form-group')->where('event_type', 'process')->exists())->toBeTrue();
});
