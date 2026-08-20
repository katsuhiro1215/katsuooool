<?php

use App\Models\ToolEvent;

test('the ui-pattern-loading page renders', function () {
    $response = $this->get(route('ui-pattern-loading.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternLoading'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-loading.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-loading')->where('event_type', 'process')->exists())->toBeTrue();
});
