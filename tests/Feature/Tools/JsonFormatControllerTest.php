<?php

use App\Models\ToolEvent;

test('the json-format page renders', function () {
    $response = $this->get(route('json-format.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('JsonFormat'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('json-format.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'json-format')->where('event_type', 'process')->exists())->toBeTrue();
});
