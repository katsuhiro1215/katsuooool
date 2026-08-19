<?php

use App\Models\ToolEvent;

test('the base64-encode page renders', function () {
    $response = $this->get(route('base64-encode.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Base64Encode'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('base64-encode.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'base64-encode')->where('event_type', 'process')->exists())->toBeTrue();
});
