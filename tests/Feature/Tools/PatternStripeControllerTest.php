<?php

use App\Models\ToolEvent;

test('the pattern-stripe page renders', function () {
    $response = $this->get(route('pattern-stripe.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternStripe'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-stripe.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-stripe')->where('event_type', 'process')->exists())->toBeTrue();
});
