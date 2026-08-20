<?php

use App\Models\ToolEvent;

test('the ui-pattern-skeleton page renders', function () {
    $response = $this->get(route('ui-pattern-skeleton.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternSkeleton'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-skeleton.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-skeleton')->where('event_type', 'process')->exists())->toBeTrue();
});
