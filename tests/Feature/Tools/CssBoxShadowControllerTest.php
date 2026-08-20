<?php

use App\Models\ToolEvent;

test('the css-box-shadow page renders', function () {
    $response = $this->get(route('css-box-shadow.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssBoxShadow'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-box-shadow.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-box-shadow')->where('event_type', 'process')->exists())->toBeTrue();
});
