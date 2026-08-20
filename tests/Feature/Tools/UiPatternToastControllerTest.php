<?php

use App\Models\ToolEvent;

test('the ui-pattern-toast page renders', function () {
    $response = $this->get(route('ui-pattern-toast.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternToast'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-toast.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-toast')->where('event_type', 'process')->exists())->toBeTrue();
});
