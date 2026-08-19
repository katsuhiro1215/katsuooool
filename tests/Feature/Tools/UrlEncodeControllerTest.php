<?php

use App\Models\ToolEvent;

test('the url-encode page renders', function () {
    $response = $this->get(route('url-encode.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UrlEncode'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('url-encode.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'url-encode')->where('event_type', 'process')->exists())->toBeTrue();
});
