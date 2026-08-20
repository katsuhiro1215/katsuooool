<?php

use App\Models\ToolEvent;

test('the ui-pattern-pagination page renders', function () {
    $response = $this->get(route('ui-pattern-pagination.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternPagination'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-pagination.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-pagination')->where('event_type', 'process')->exists())->toBeTrue();
});
