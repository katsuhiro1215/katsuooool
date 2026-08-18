<?php

use App\Models\ToolEvent;

test('the color-contrast page renders', function () {
    $response = $this->get(route('color-contrast.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorContrast'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-contrast.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-contrast')->where('event_type', 'process')->exists())->toBeTrue();
});
