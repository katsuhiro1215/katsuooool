<?php

use App\Models\ToolEvent;

test('the color-convert page renders', function () {
    $response = $this->get(route('color-convert.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ColorConvert'));
});

test('posting to the track endpoint logs a process event and does not require file data', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-convert.track'));

    $response->assertSuccessful();

    $event = ToolEvent::where('tool_slug', 'color-convert')->where('event_type', 'process')->first();
    expect($event)->not->toBeNull();
    expect($event->file_count)->toBeNull();
    expect($event->input_bytes)->toBeNull();
});
