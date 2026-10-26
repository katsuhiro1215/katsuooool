<?php

use App\Models\ToolEvent;

test('the hearing-sheet page renders', function () {
    $this->get('/hearing-sheet')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('HearingSheet'));
});

test('posting to the track endpoint logs a process event', function () {
    $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/hearing-sheet/track')
        ->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'hearing-sheet')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the tool list has the hearing sheet in the generator category', function () {
    $tool = collect(config('tools'))->firstWhere('slug', 'hearing-sheet');

    expect($tool)->not->toBeNull()
        ->and($tool['category'])->toBe('generator')
        ->and($tool['href'])->toBe('/hearing-sheet');
});
