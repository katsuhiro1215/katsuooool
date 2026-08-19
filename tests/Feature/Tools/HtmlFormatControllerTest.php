<?php

use App\Models\ToolEvent;

test('the html-format page renders', function () {
    $response = $this->get(route('html-format.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('HtmlFormat'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('html-format.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'html-format')->where('event_type', 'process')->exists())->toBeTrue();
});
