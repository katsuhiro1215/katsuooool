<?php

use App\Models\ToolEvent;

test('the seo-title-check page renders', function () {
    $response = $this->get(route('seo-title-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('SeoTitleCheck'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('seo-title-check.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'seo-title-check')->where('event_type', 'process')->exists())->toBeTrue();
});
