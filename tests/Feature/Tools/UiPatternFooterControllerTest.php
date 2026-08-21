<?php

use App\Models\ToolEvent;

test('the ui-pattern-footer page renders', function () {
    $response = $this->get(route('ui-pattern-footer.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFooter'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-footer.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-footer')->where('event_type', 'process')->exists())->toBeTrue();
});
