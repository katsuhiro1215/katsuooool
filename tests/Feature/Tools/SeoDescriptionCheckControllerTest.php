<?php

use App\Models\ToolEvent;

test('the seo-description-check page renders', function () {
    $response = $this->get(route('seo-description-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('SeoDescriptionCheck'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('seo-description-check.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'seo-description-check')->where('event_type', 'process')->exists())->toBeTrue();
});
