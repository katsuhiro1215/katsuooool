<?php

use App\Models\ToolEvent;

test('the svg-optimize page renders', function () {
    $response = $this->get(route('svg-optimize.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('SvgOptimize'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('svg-optimize.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'svg-optimize')->where('event_type', 'process')->exists())->toBeTrue();
});
