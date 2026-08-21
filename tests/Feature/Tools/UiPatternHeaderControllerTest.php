<?php

use App\Models\ToolEvent;

test('the ui-pattern-header page renders', function () {
    $response = $this->get(route('ui-pattern-header.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternHeader'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-header.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-header')->where('event_type', 'process')->exists())->toBeTrue();
});
