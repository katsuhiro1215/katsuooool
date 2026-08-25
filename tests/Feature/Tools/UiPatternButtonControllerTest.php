<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the show page only includes button-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'カードパターン',
        'html' => '<div>card</div>',
        'css' => '.card {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '斜線シャドウボタン',
        'html' => '<a href="#"><span>Button</span></a>',
        'css' => 'a { display: block; }',
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', '斜線シャドウボタン')
    );
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-button.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-button')->where('event_type', 'process')->exists())->toBeTrue();
});
