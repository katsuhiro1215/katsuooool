<?php

use App\Models\ToolEvent;

test('the generator-dummy-data page renders', function () {
    $response = $this->get(route('generator-dummy-data.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('GeneratorDummyData'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('generator-dummy-data.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'generator-dummy-data')->where('event_type', 'process')->exists())->toBeTrue();
});
