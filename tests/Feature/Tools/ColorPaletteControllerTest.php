<?php

use App\Models\ToolEvent;

test('the color-palette page renders', function () {
    $response = $this->get(route('color-palette.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorPalette'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-palette.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'color-palette')->where('event_type', 'process')->exists())->toBeTrue();
});
